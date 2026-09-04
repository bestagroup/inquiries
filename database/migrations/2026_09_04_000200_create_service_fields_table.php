<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('direction', 16);
            $table->string('key', 128);
            $table->string('label');
            $table->string('type', 24)->default('text');
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->text('default_value')->nullable();
            $table->string('json_path', 512)->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['service_id', 'direction', 'key'], 'service_fields_service_direction_key_unique');
            $table->index(['service_id', 'direction', 'sort_order'], 'service_fields_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fields');
    }
};
