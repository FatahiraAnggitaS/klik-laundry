<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone', 30);
            $table->string('onboarding_status', 24)->default('pending');
            $table->string('operational_status', 24)->default('inactive');
            $table->text('review_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('closure_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('payout_hold')->default(false);
            $table->text('payout_hold_reason')->nullable();
            $table->foreignId('payout_hold_set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('initial_outlet_name');
            $table->text('initial_outlet_address');
            $table->string('initial_outlet_city', 120);
            $table->string('initial_outlet_area', 120);
            $table->decimal('initial_outlet_latitude', 10, 7);
            $table->decimal('initial_outlet_longitude', 10, 7);
            $table->timestamps();

            $table->index(['onboarding_status', 'operational_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
