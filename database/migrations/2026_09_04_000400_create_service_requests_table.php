<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('execution_token')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->longText('input_payload')->comment('Encrypted array cast');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_requested_at')->nullable()->index();
            $table->timestamp('last_responded_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->unsignedInteger('last_duration_ms')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['service_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['status', 'updated_at']);
            $table->index(['user_id', 'service_id', 'created_at'], 'service_requests_user_service_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
