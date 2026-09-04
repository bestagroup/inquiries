<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status', 24)->default('running')->index();
            $table->string('endpoint_url', 2048);
            $table->string('http_method', 10);
            $table->longText('request_payload')->nullable()->comment('Encrypted array cast');
            $table->longText('response_payload')->nullable()->comment('Encrypted array cast');
            $table->longText('mapped_response')->nullable()->comment('Encrypted array cast; stable output snapshot');
            $table->longText('response_raw')->nullable()->comment('Encrypted string cast');
            $table->unsignedSmallInteger('http_status')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['service_request_id', 'sequence'], 'service_request_attempt_sequence_unique');
            $table->index(['service_request_id', 'created_at'], 'service_request_attempt_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_attempts');
    }
};
