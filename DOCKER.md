# Docker Setup - Laravel + RabbitMQ

## المحتويات
- [Architecture الكاملة](#architecture)
- [لماذا كان الموقع بطيئاً وكيف تم الحل](#بطء-الموقع)
- [OPcache](#opcache)
- [كيف نزّلنا RabbitMQ](#rabbitmq)
- [لماذا Nginx + PHP-FPM](#nginx--php-fpm)
- [أوامر مفيدة](#أوامر-مفيدة)

---

## Architecture

```
                   ┌──────────────────────────────────────────────┐
                   │               Docker Network                  │
 Browser           │                                              │
   │               │  ┌─────────┐   FastCGI    ┌──────────────┐  │
   │── port 8000 ──┼─►│  Nginx  │─────────────►│  PHP-FPM     │  │
   │               │  └────┬────┘  port 9000   │  (laravel)   │  │
   │               │       │                   └──────┬───────┘  │
   │── port 15672 ─┼──┐    │ static files             │          │
   │  (RabbitMQ UI)│  │    ▼                          ▼          │
   │               │  │  public_data            ┌──────────┐     │
   │── port 5672 ──┼──┼── (volume) ─────────────►  MySQL   │     │
   │   (AMQP)      │  │                         └──────────┘     │
   │               │  │                              │           │
   │               │  │                         ┌────────────┐   │
   │               │  └────────────────────────►  RabbitMQ  │   │
   │               │                            └────────────┘   │
                   └──────────────────────────────────────────────┘
```

| Port  | Service    | الاستخدام                        |
|-------|------------|----------------------------------|
| 8000  | Nginx      | الموقع                           |
| 15672 | RabbitMQ   | Management UI (guest/guest)      |
| 5672  | RabbitMQ   | AMQP - يستخدمه التطبيق           |
| 3306  | MySQL      | قاعدة البيانات                   |

---

## بطء الموقع

### المشكلة: Windows Volume Mount

الإعداد الأول في `docker-compose.yml` كان:

```yaml
volumes:
  - .:/var/www  # ❌ هذا كان سبب البطء
```

**ما يحدث بالضبط:**

Docker على Windows يعمل داخل **WSL2** (طبقة Linux وهمية).
الكود موجود في `C:\code\RabbitMQ` على **Windows NTFS filesystem**.
كل مرة PHP تقرأ ملف، يحدث هذا:

```
Browser Request
      ↓
  Nginx (Linux/WSL2)
      ↓
  PHP-FPM (Linux/WSL2)
      ↓
  يطلب قراءة ملف PHP
      ↓
  WSL2 → يعبر الـ bridge → Windows NTFS Filesystem
      ↓                            ↑
  تأخير 50-200ms لكل ملف ←←←←←←←←
```

Laravel في كل request يقرأ **أكثر من 200 ملف** (framework, config, routes, views).
مع 50ms لكل ملف = **+10 ثوانٍ** في كل request.

### الحل: نقل الكود داخل الـ Image

في [Dockerfile](Dockerfile) نستخدم:

```dockerfile
COPY . .  # ✅ الكود يُنسخ داخل الـ image وقت البناء
```

وفي [docker-compose.yml](docker-compose.yml) نربط فقط ما يحتاج يتغير:

```yaml
volumes:
  - storage_data:/var/www/storage  # logs, uploads - تتغير وقت التشغيل
  - public_data:/var/www/public    # مشترك مع nginx للملفات الثابتة
```

الآن PHP تقرأ الملفات من **Linux filesystem مباشرة داخل الـ container**:

```
Browser Request
      ↓
  Nginx (Linux)
      ↓
  PHP-FPM (Linux)
      ↓
  يقرأ الملف من Linux filesystem ✅
      ↓
  أقل من 1ms لكل ملف
```

> **ملاحظة مهمة:** بسبب هذا الإعداد، أي تعديل على كود PHP يتطلب إعادة بناء الـ image:
> ```bash
> docker compose build app && docker compose up -d app
> ```

---

## OPcache

مضاف في [Dockerfile](Dockerfile):

```dockerfile
&& docker-php-ext-install opcache \
&& docker-php-ext-enable amqp opcache \
```

مع إعدادات الأداء:

```ini
opcache.enable=1
opcache.memory_consumption=256       # 256MB في RAM للـ cache
opcache.max_accelerated_files=20000  # أقصى عدد ملفات محفوظة
opcache.validate_timestamps=1        # يتحقق من تغيير الملفات
opcache.revalidate_freq=0            # يتحقق في كل request
```

**بدون OPcache - كل request من الصفر:**

```
Request 1 → يقرأ الملف → يحوله لـ Bytecode → ينفذه → يحذفه
Request 2 → يقرأ الملف → يحوله لـ Bytecode → ينفذه → يحذفه
Request 3 → يقرأ الملف → يحوله لـ Bytecode → ينفذه → يحذفه
```

**مع OPcache - مرة واحدة بس:**

```
Request 1 → يقرأ الملف → يحوله لـ Bytecode → يحفظه في RAM → ينفذه
Request 2 → من RAM مباشرة ✅ (أسرع 10x)
Request 3 → من RAM مباشرة ✅
Request 4 → من RAM مباشرة ✅
```

---

## RabbitMQ

### التثبيت في docker-compose.yml

```yaml
rabbitmq:
  image: rabbitmq:3.13-management  # management = يجي مع UI
  environment:
    RABBITMQ_DEFAULT_USER: guest
    RABBITMQ_DEFAULT_PASS: guest
  ports:
    - "5672:5672"    # AMQP - التطبيق يتكلم عليه
    - "15672:15672"  # Management UI - الواجهة البصرية
  volumes:
    - rabbitmq_data:/var/lib/rabbitmq  # يحفظ الـ queues عند restart
```

### تثبيت PHP Extension للتواصل مع RabbitMQ

في [Dockerfile](Dockerfile):

```dockerfile
# 1. مكتبة C للتواصل مع RabbitMQ (مطلوبة للـ pecl)
apt-get install librabbitmq-dev

# 2. PHP extension تستخدم المكتبة
pecl install amqp

# 3. تفعيلها في PHP
docker-php-ext-enable amqp
```

### إعدادات الاتصال في .env

```env
RABBITMQ_HOST=rabbitmq    # اسم الـ service في docker-compose
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
```

> **لماذا `rabbitmq` وليس `localhost`؟**
> داخل Docker network، كل container يعرف الآخرين باسم الـ service.
> `rabbitmq` هو اسم الـ service المعرّف في `docker-compose.yml`.

### الفرق بين الـ Ports

```
Port 5672 (AMQP):
  Laravel ──────────────► RabbitMQ
  (ترسل message للـ queue)     (يحفظها)

  Worker ◄──────────────  RabbitMQ
  (يسحب message ويشتغل عليها)

Port 15672 (Management UI):
  Browser ──► http://localhost:15672 ──► واجهة مرئية
  - تشوف الـ queues
  - تشوف الـ messages
  - تشوف الـ consumers
  - تراقب الأداء
```

---

## Nginx + PHP-FPM

### لماذا Nginx وليس Apache؟

| | Nginx | Apache |
|---|---|---|
| الأسلوب | Event-driven (async) | Process-based |
| 1000 connection في نفس الوقت | thread واحد يتعامل معها | 1000 process |
| RAM لـ 1000 connection | ~50MB | ~500MB |
| الملفات الثابتة | سريع جداً (native) | أبطأ |

### لماذا PHP-FPM وليس mod_php؟

**mod_php (Apache):**
```
كل request → Apache يشغّل PHP process → يخلص → يوقفه
كل request → Apache يشغّل PHP process → يخلص → يوقفه
(overhead في كل مرة: بداية PHP، تحميل extensions، إلخ)
```

**PHP-FPM:**
```
عند البداية: يشغّل Pool من الـ processes جاهزة ومحملة
[PHP-1] [PHP-2] [PHP-3] [PHP-4] ... جاهزين ينتظرون

Request 1 ──► [PHP-1] يشتغل ─► يرجع جاهز
Request 2 ──► [PHP-2] يشتغل ─► في نفس الوقت!
Request 3 ──► [PHP-3] يشتغل ─► في نفس الوقت!
```

### كيف يتواصلان (FastCGI)

```
[Browser] ──HTTP──► [Nginx :80]
                         │
                    هل هو PHP؟
                    ┌────┴────┐
                   نعم       لا
                    │         │
              FastCGI      يرسله
              ──────►      مباشرة
           [PHP-FPM :9000]
                    │
              ينفذ الكود
                    │
              يرجع HTML
                    │
              [Nginx] ──HTTP──► [Browser]
```

إعداد هذا التوجيه في [docker/nginx/nginx.conf](docker/nginx/nginx.conf):

```nginx
# الملفات الثابتة → Nginx مباشرة (بدون PHP)
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# ملفات PHP → PHP-FPM
location ~ \.php$ {
    fastcgi_pass app:9000;   # app = اسم container الـ PHP
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### لماذا Container منفصل لكل واحد؟

```
Container واحد (Nginx + PHP):          Containers منفصلة:
┌────────────────────┐                 ┌─────────┐  ┌──────────┐
│  Nginx + PHP-FPM   │                 │  Nginx  │  │ PHP-FPM  │
│                    │                 └─────────┘  └──────────┘
│  مشكلة:           │
│  - صعب التحديث    │                 مزايا:
│  - صعب الـ scale  │                 ✅ تحديث كل واحد بشكل مستقل
│  - موارد مختلطة   │                 ✅ scale PHP بدون Nginx
└────────────────────┘                 ✅ monitoring منفصل
```

---

## أوامر مفيدة

```bash
# تشغيل كل شيء
docker compose up -d

# إيقاف كل شيء
docker compose down

# بعد تعديل كود PHP - إعادة البناء
docker compose build app && docker compose up -d app

# مشاهدة logs
docker compose logs -f           # كل الـ containers
docker compose logs -f app       # PHP فقط
docker compose logs -f nginx     # Nginx فقط
docker compose logs -f rabbitmq  # RabbitMQ فقط

# الدخول على الـ container
docker compose exec app bash

# تشغيل artisan commands
docker compose exec app php artisan migrate
docker compose exec app php artisan queue:work

# إعادة تشغيل container واحد
docker compose restart app

# حذف كل شيء (بما فيها الـ volumes - تحذير: يحذف DB)
docker compose down -v
```

---

## ملفات المشروع

```
.
├── Dockerfile                  # بناء صورة PHP
├── docker-compose.yml          # تعريف كل الـ services
├── docker/
│   └── nginx/
│       └── nginx.conf          # إعدادات Nginx
├── .env                        # إعدادات البيئة (لا ترفعه لـ git)
└── .env.example                # نموذج الإعدادات
```
