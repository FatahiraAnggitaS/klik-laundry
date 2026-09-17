<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('contact_phone', 30);
            $table->text('address');
            $table->string('city', 120);
            $table->string('area', 120);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('service_radius_m')->default(1000);
            $table->unsignedBigInteger('pickup_fee')->default(0);
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->string('status', 24)->default('draft');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'city', 'area']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('outlet_operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->unique(['outlet_id', 'day_of_week']);
        });

        Schema::create('outlet_slots', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['outlet_id', 'type', 'day_of_week', 'starts_at', 'ends_at'], 'outlet_slots_natural_unique');
            $table->index(['outlet_id', 'type', 'is_active']);
        });

        Schema::create('outlet_blackouts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['outlet_id', 'date']);
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('pricing_type', 16);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('minimum_quantity')->nullable();
            $table->unsignedInteger('minimum_weight_grams')->nullable();
            $table->unsignedInteger('estimated_duration_minutes');
            $table->string('status', 24)->default('draft');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'pricing_type', 'status']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('default_customer_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->string('label', 80);
            $table->string('contact_name');
            $table->string('contact_phone', 30);
            $table->text('address');
            $table->string('city', 120);
            $table->string('area', 120);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('location_consented_at');
            $table->timestamps();

            $table->index(['customer_id', 'area']);
        });

        DB::table('tenants')
            ->orderBy('id')
            ->get()
            ->each(function (object $tenant): void {
                DB::table('outlets')->insert([
                    'public_id' => (string) Str::ulid(),
                    'tenant_id' => $tenant->id,
                    'name' => $tenant->initial_outlet_name,
                    'contact_phone' => $tenant->phone,
                    'address' => $tenant->initial_outlet_address,
                    'city' => $tenant->initial_outlet_city,
                    'area' => $tenant->initial_outlet_area,
                    'latitude' => $tenant->initial_outlet_latitude,
                    'longitude' => $tenant->initial_outlet_longitude,
                    'service_radius_m' => 1000,
                    'pickup_fee' => 0,
                    'delivery_fee' => 0,
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('outlet_blackouts');
        Schema::dropIfExists('outlet_slots');
        Schema::dropIfExists('outlet_operating_hours');
        Schema::dropIfExists('outlets');
    }
};
