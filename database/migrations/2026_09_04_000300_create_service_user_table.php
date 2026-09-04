<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'service_id']);
            $table->index(['service_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_user');
    }
};
