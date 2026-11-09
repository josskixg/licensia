<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table): void {
            $table->id();
            $table->string('license_id', 64)->unique();
            $table->string('product_id', 64)->index();
            $table->string('customer_email', 191)->index();
            $table->string('tier', 32)->default('pro');
            $table->unsignedSmallInteger('max_seats')->default(5);
            $table->string('environment_binding_hash', 64)->default('*');
            $table->string('environment_label', 191)->default('*');
            $table->text('certificate');
            $table->string('signature_hex', 128);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('license_activations', function (Blueprint $table): void {
            $table->id();
            $table->string('license_id', 64)->index();
            $table->string('fingerprint_hash', 64)->index();
            $table->string('domain_or_hwid', 191);
            $table->string('ip_address', 45)->nullable();
            $table->string('php_version', 32)->nullable();
            $table->timestamp('activated_at');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();

            $table->unique(['license_id', 'fingerprint_hash']);
        });

        Schema::create('license_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('license_id', 64)->index();
            $table->string('event_type', 48)->index(); // ISSUED, SEAT_ACTIVATED, TAMPER_REJECTED, REVOKED
            $table->string('status_code', 48);
            $table->string('domain_or_hwid', 191)->nullable();
            $table->json('telemetry_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_audit_logs');
        Schema::dropIfExists('license_activations');
        Schema::dropIfExists('licenses');
    }
};
