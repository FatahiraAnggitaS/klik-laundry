<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_driver_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pickup_commission')->nullable();
            $table->unsignedBigInteger('delivery_commission')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('driver_invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('phone', 30);
            $table->char('token_hash', 64)->unique();
            $table->string('active_email_key')->nullable()->unique();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['email', 'expires_at']);
        });

        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('availability', 24)->default('unavailable');
            $table->timestamps();

            $table->index(['tenant_id', 'availability']);
        });

        Schema::create('delivery_tasks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->string('status', 24)->default('pending');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('active_driver_key')->nullable()->unique();
            $table->unsignedBigInteger('commission_amount');
            $table->timestamp('scheduled_starts_at');
            $table->timestamp('scheduled_ends_at');
            $table->text('note')->nullable();
            $table->string('proof_disk', 32)->nullable();
            $table->string('proof_key')->nullable();
            $table->string('proof_mime', 64)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'type']);
            $table->index(['tenant_id', 'status', 'scheduled_starts_at']);
            $table->index(['assignee_id', 'status']);
        });

        Schema::create('driver_task_offers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('task_id')->constrained('delivery_tasks')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('offered');
            $table->string('active_task_key')->nullable()->unique();
            $table->timestamp('offered_at');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status', 'expires_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('driver_task_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('delivery_tasks')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['task_id', 'occurred_at']);
        });

        Schema::create('driver_commissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->unique()->constrained('delivery_tasks')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('earned');
            $table->timestamp('earned_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status', 'earned_at']);
            $table->index(['tenant_id', 'status', 'earned_at']);
        });

        Schema::create('weight_confirmations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('actual_grams');
            $table->unsignedInteger('minimum_grams');
            $table->unsignedInteger('billable_grams');
            $table->unsignedSmallInteger('rounding_increment_grams')->default(100);
            $table->unsignedBigInteger('items_subtotal');
            $table->unsignedBigInteger('grand_total');
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->string('current_order_key')->nullable()->unique();
            $table->foreignId('superseded_by')->nullable()->constrained('weight_confirmations')->nullOnDelete();
            $table->string('proof_disk', 32)->nullable();
            $table->string('proof_key')->nullable();
            $table->string('proof_mime', 64)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->index(['order_id', 'confirmed_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('actual_weight_grams')->nullable()->after('estimated_billable_weight_grams');
            $table->unsignedInteger('billable_weight_grams')->nullable()->after('actual_weight_grams');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['actual_weight_grams', 'billable_weight_grams']);
        });
        Schema::dropIfExists('weight_confirmations');
        Schema::dropIfExists('driver_commissions');
        Schema::dropIfExists('driver_task_histories');
        Schema::dropIfExists('driver_task_offers');
        Schema::dropIfExists('delivery_tasks');
        Schema::dropIfExists('driver_profiles');
        Schema::dropIfExists('driver_invitations');
        Schema::dropIfExists('tenant_driver_settings');
    }
};
