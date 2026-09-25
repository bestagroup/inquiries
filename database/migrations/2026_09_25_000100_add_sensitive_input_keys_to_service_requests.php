<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->longText('sensitive_input_keys')->nullable()->comment('Encrypted array cast');
        });

        Schema::table('service_request_attempts', function (Blueprint $table): void {
            $table->longText('sensitive_input_keys')->nullable()->comment('Encrypted array cast');
        });
    }

    public function down(): void
    {
        Schema::table('service_request_attempts', function (Blueprint $table): void {
            $table->dropColumn('sensitive_input_keys');
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn('sensitive_input_keys');
        });
    }
};
