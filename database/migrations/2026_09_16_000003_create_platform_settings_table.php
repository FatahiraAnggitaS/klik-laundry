<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 32)->primary();
            $table->unsignedSmallInteger('max_service_radius_km');
            $table->boolean('payment_maintenance_enabled')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        DB::table('platform_settings')->insert([
            'key' => 'global',
            'max_service_radius_km' => 20,
            'payment_maintenance_enabled' => false,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
