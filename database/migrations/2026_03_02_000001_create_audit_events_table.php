<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 100)->index();
            $table->string('source_service', 100)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('entity_id', 100)->nullable()->index();
            $table->json('payload');
            $table->unsignedSmallInteger('risk_score')->default(0)->index();
            $table->boolean('alert_triggered')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
