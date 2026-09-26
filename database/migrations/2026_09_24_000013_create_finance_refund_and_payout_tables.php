<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('status', 24);
            $table->unsignedBigInteger('amount');
            $table->text('reason');
            $table->string('active_payment_key')->nullable()->unique();
            $table->string('completed_payment_key')->nullable()->unique();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('transfer_method', 32)->nullable();
            $table->string('external_reference', 120)->nullable()->unique();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['payment_id', 'status']);
        });

        Schema::create('financial_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('refund_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->bigInteger('amount');
            $table->text('reason');
            $table->string('settlement_status', 16)->default('unsettled');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'settlement_status', 'occurred_at'], 'adjustment_settlement_idx');
        });

        Schema::create('tenant_payouts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('batch_reference', 64)->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('payout_account_id')->constrained('tenant_payout_accounts')->restrictOnDelete();
            $table->string('status', 24);
            $table->timestamp('cutoff_at');
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('fee_amount');
            $table->bigInteger('adjustment_amount');
            $table->bigInteger('net_amount');
            $table->string('pending_tenant_key')->nullable()->unique();
            $table->string('bank_name');
            $table->text('account_holder_name');
            $table->text('account_number');
            $table->string('masked_account_number', 80);
            $table->string('transfer_method', 32)->nullable();
            $table->string('external_reference', 120)->nullable()->unique();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'cutoff_at']);
        });

        Schema::create('tenant_payout_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_payout_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('fee_amount');
            $table->unsignedBigInteger('net_amount');
            $table->string('active_payment_key')->nullable()->unique();
            $table->string('finalized_payment_key')->nullable()->unique();
            $table->timestamps();

            $table->unique(['tenant_payout_id', 'payment_id']);
        });

        Schema::create('tenant_payout_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_payout_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_adjustment_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->string('active_adjustment_key')->nullable()->unique();
            $table->string('finalized_adjustment_key')->nullable()->unique();
            $table->timestamps();

            $table->unique(['tenant_payout_id', 'financial_adjustment_id'], 'tenant_payout_adjustment_unique');
        });

        Schema::create('driver_payouts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('batch_reference', 64)->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24);
            $table->timestamp('cutoff_at');
            $table->unsignedBigInteger('total_amount');
            $table->string('pending_driver_key')->nullable()->unique();
            $table->string('transfer_method', 32)->nullable();
            $table->string('external_reference', 120)->nullable()->unique();
            $table->text('note')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'driver_id', 'status', 'cutoff_at'], 'driver_payout_lookup_idx');
        });

        Schema::create('driver_payout_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_payout_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_commission_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('active_commission_key')->nullable()->unique();
            $table->string('finalized_commission_key')->nullable()->unique();
            $table->timestamps();

            $table->unique(['driver_payout_id', 'driver_commission_id'], 'driver_payout_commission_unique');
        });
    }

    public function down(): void
    {
        foreach (['refund_requests', 'financial_adjustments', 'tenant_payouts', 'driver_payouts'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Milestone 8 finance data exists; rollback is intentionally blocked.');
            }
        }

        Schema::dropIfExists('driver_payout_items');
        Schema::dropIfExists('driver_payouts');
        Schema::dropIfExists('tenant_payout_adjustments');
        Schema::dropIfExists('tenant_payout_items');
        Schema::dropIfExists('tenant_payouts');
        Schema::dropIfExists('financial_adjustments');
        Schema::dropIfExists('refund_requests');
    }
};
