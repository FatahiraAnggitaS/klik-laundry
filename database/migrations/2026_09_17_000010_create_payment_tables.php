<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_channels', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24)->default('duitku');
            $table->string('channel_code', 16);
            $table->string('label', 64);
            $table->string('category', 16);
            $table->boolean('is_active')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'channel_code']);
            $table->index(['category', 'is_active']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('merchant_order_id', 64)->unique();
            $table->string('provider_reference', 64)->nullable()->unique();
            $table->string('channel_code', 16);
            $table->unsignedBigInteger('amount');
            $table->string('status', 16)->default('pending');
            $table->string('reconciliation', 24)->default('not_required');
            $table->text('provider_payment_url')->nullable();
            $table->unsignedBigInteger('fee_amount')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('terminal_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->string('provider_status', 16);
            $table->string('provider_reference', 64)->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->boolean('signature_ok');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['payment_id', 'processed_at']);
        });

        DB::table('payment_channels')->insert([
            ['provider' => 'duitku', 'channel_code' => 'SP', 'label' => 'QRIS ShopeePay', 'category' => 'qris', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'duitku', 'channel_code' => 'NQ', 'label' => 'QRIS Nobu', 'category' => 'qris', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'duitku', 'channel_code' => 'SQ', 'label' => 'QRIS Nusapay', 'category' => 'qris', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'duitku', 'channel_code' => 'GQ', 'label' => 'QRIS Gudang Voucher', 'category' => 'qris', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'duitku', 'channel_code' => 'OV', 'label' => 'OVO', 'category' => 'e_wallet', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'duitku', 'channel_code' => 'SA', 'label' => 'ShopeePay', 'category' => 'e_wallet', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'duitku', 'channel_code' => 'DA', 'label' => 'DANA', 'category' => 'e_wallet', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_channels');
    }
};
