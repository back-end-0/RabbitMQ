# Cherry-Pick Commit to Develop Branch

## الهدف
نقل كوميت معين (`e1536b99`) من branch ثاني إلى `develop` عن طريق إنشاء branch جديد و Pull Request.

---

## مفاهيم أساسية

### ما معنى `HEAD`؟

**`HEAD`** = الكوميت الحالي (آخر كوميت أنت واقف عليه)

**`~N`** = ارجع N خطوات للخلف

| الرمز | المعنى |
|-------|--------|
| `HEAD` | الكوميت الحالي |
| `HEAD~1` | قبل كوميت واحد |
| `HEAD~2` | قبل كوميتين |
| `HEAD~3` | قبل 3 كوميتات |

```
commit C  ← HEAD      (الحالي)
commit B  ← HEAD~1    (الي قبله)
commit A  ← HEAD~2    (الي قبل قبله)
```

---

### أوامر التراجع (Reset)

#### `git reset --soft HEAD~1`
> **التراجع عن آخر كوميت مع الاحتفاظ بالتغييرات في staging area**

- يشيل الكوميت بس الملفات تفضل جاهزة للكوميت تاني
- يعني: "شيل الكوميت بس خلّي الملفات زي ما هي"

#### `git reset HEAD .`
> **إزالة الملفات من staging area (unstage)**

- التعديلات تفضل موجودة في الملفات بس مش جاهزة للكوميت

#### `git checkout -- .`
> **حذف كل التعديلات نهائياً من الملفات**

- يرجّع الملفات لآخر نسخة محفوظة في الكوميت

#### الثلاثة مع بعض:
```bash
git reset --soft HEAD~1     # ← تراجع عن الكوميت، التغييرات لسه موجودة
git reset HEAD .            # ← شيل التغييرات من staging
git checkout -- .           # ← احذف التغييرات نهائياً من الملفات
```
**النتيجة:** كأن الكوميت ما صار أصلاً - الـ branch يرجع نظيف.

#### الفرق بين أنواع Reset:

| النوع | الكوميت | Staging Area | الملفات |
|-------|---------|--------------|---------|
| `--soft` | يتشال | تفضل موجودة | تفضل موجودة |
| `--mixed` (افتراضي) | يتشال | تتشال | تفضل موجودة |
| `--hard` | يتشال | تتشال | تتشال |

---

## خطوات العمل

### 1. التأكد من الكوميت
```bash
git log --oneline -1 e1536b994aeabdee65c838146e4b2e398194dc28
```
> يعرض تفاصيل الكوميت للتأكد إنه الصحيح

**النتيجة:**
```
e1536b994 fix: reduce app container CPU/memory limits to match 8-core server
```

---

### 2. التبديل إلى develop وتحديثه
```bash
git checkout develop
git pull origin develop
```
> ننتقل لـ develop ونسحب آخر التحديثات من السيرفر

---

### 3. إنشاء branch جديد من develop
```bash
git checkout -b fix/reduce-cpu-memory-limits develop
```
> ننشئ branch جديد اسمه `fix/reduce-cpu-memory-limits` مبني على `develop`

---

### 4. نقل الكوميت (Cherry-Pick)
```bash
git cherry-pick e1536b994aeabdee65c838146e4b2e398194dc28
```
> ينسخ الكوميت المحدد ويطبقه على الـ branch الجديد

---

### 5. رفع الـ branch للسيرفر
```bash
git push -u origin fix/reduce-cpu-memory-limits
```
> يرفع الـ branch الجديد على GitHub ويربطه بالريموت

---

### 6. إنشاء Pull Request
```bash
gh pr create --base develop \
  --title "fix: reduce app container CPU/memory limits to match 8-core server" \
  --body "## Summary
- Reduce app container CPU/memory limits to match 8-core server configuration

## Test plan
- [ ] Verify container starts successfully with new resource limits
- [ ] Confirm resource usage stays within updated bounds"
```
> ينشئ Pull Request يستهدف branch الـ `develop`

**أو من المتصفح:**
```
https://github.com/Almusanid-co/matryal-back/pull/new/fix/reduce-cpu-memory-limits
```
> تأكد تختار **develop** كـ base branch

---

## ملخص سريع

| الخطوة | الأمر | الوصف |
|--------|-------|-------|
| 1 | `git log` | التأكد من الكوميت |
| 2 | `git checkout develop && git pull` | تحديث develop |
| 3 | `git checkout -b fix/...` | إنشاء branch جديد |
| 4 | `git cherry-pick <hash>` | نقل الكوميت |
| 5 | `git push -u origin fix/...` | رفع الـ branch |
| 6 | `gh pr create` | إنشاء Pull Request |
