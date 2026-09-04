<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('endpoint_url', 2048);
            $table->string('http_method', 10)->default('POST');
            $table->string('payload_mode', 16)->default('json');
            $table->string('response_format', 16)->default('json');
            $table->text('headers')->nullable()->comment('Encrypted array cast');
            $table->unsignedTinyInteger('timeout_seconds')->default(15);
            $table->unsignedTinyInteger('connect_timeout_seconds')->default(5);
            $table->unsignedTinyInteger('retry_times')->default(1);
            $table->unsignedInteger('retry_delay_ms')->default(200);
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->boolean('allow_resubmit')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
