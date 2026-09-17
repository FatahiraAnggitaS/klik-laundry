<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('order_number', 64)->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('tenant_name');
            $table->string('outlet_name');
            $table->uuid('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->string('pricing_type', 16);
            $table->string('fulfillment_status', 32);
            $table->string('payment_status', 16);
            $table->foreignId('pickup_slot_id')->constrained('outlet_slots')->restrictOnDelete();
            $table->timestamp('pickup_starts_at');
            $table->timestamp('pickup_ends_at');
            $table->foreignId('delivery_slot_id')->nullable()->constrained('outlet_slots')->restrictOnDelete();
            $table->timestamp('delivery_starts_at')->nullable();
            $table->timestamp('delivery_ends_at')->nullable();
            $table->unsignedBigInteger('items_subtotal')->nullable();
            $table->unsignedBigInteger('estimated_items_subtotal')->nullable();
            $table->unsignedBigInteger('pickup_fee');
            $table->unsignedBigInteger('delivery_fee');
            $table->unsignedBigInteger('grand_total')->nullable();
            $table->unsignedBigInteger('estimated_grand_total')->nullable();
            $table->timestamp('estimated_ready_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'idempotency_key']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['tenant_id', 'fulfillment_status', 'created_at']);
            $table->index(['outlet_id', 'fulfillment_status', 'pickup_starts_at']);
            $table->index(['payment_status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
            $table->string('package_name');
            $table->text('package_description')->nullable();
            $table->string('pricing_type', 16);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('minimum_quantity')->nullable();
            $table->unsignedInteger('minimum_weight_grams')->nullable();
            $table->unsignedInteger('estimated_duration_minutes');
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('estimated_weight_grams')->nullable();
            $table->unsignedInteger('estimated_billable_weight_grams')->nullable();
            $table->timestamps();
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('source_public_id', 26)->nullable();
            $table->string('label', 80);
            $table->string('contact_name');
            $table->string('contact_phone', 30);
            $table->text('address');
            $table->string('city', 120);
            $table->string('area', 120);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();

            $table->unique(['order_id', 'type']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['order_id', 'occurred_at']);
        });

        Schema::create('order_schedule_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('schedule_type', 16);
            $table->foreignId('old_slot_id')->constrained('outlet_slots')->restrictOnDelete();
            $table->timestamp('old_starts_at');
            $table->timestamp('old_ends_at');
            $table->foreignId('new_slot_id')->constrained('outlet_slots')->restrictOnDelete();
            $table->timestamp('new_starts_at');
            $table->timestamp('new_ends_at');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['order_id', 'occurred_at']);
        });

        Schema::create('order_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('active_key', 96)->nullable()->unique();
            $table->json('context')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'type', 'detected_at']);
            $table->index(['type', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_indicators');
        Schema::dropIfExists('order_schedule_histories');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
