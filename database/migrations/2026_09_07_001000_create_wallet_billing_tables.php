<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedBigInteger('price_amount')->default(0);
        });

        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('reserved_balance')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach (DB::table('users')->pluck('id') as $userId) {
            DB::table('wallets')->insert([
                'user_id' => $userId,
                'balance' => 0,
                'reserved_balance' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('wallet_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->uuid('execution_token')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('reserved');
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'status']);
        });

        Schema::table('service_request_attempts', function (Blueprint $table): void {
            $table->unsignedBigInteger('price_amount')->default(0);
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_request_attempt_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');

        Schema::table('service_request_attempts', function (Blueprint $table): void {
            $table->dropColumn('price_amount');
        });

        Schema::dropIfExists('wallet_reservations');
        Schema::dropIfExists('wallets');

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('price_amount');
        });
    }
};
