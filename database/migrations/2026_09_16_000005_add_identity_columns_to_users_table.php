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
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->unique()->after('id');
            $table->foreignId('tenant_id')->nullable()->after('public_id')->constrained()->restrictOnDelete();
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('role', 24)->default('customer')->after('tenant_id');
            $table->string('status', 24)->default('active')->after('role');
            $table->text('status_reason')->nullable()->after('status');
            $table->unsignedBigInteger('auth_version')->default(1)->after('status_reason');
            $table->string('role_slot')->nullable()->unique()->after('auth_version');
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('suspended_at')->nullable()->after('two_factor_confirmed_at');
            $table->timestamp('closed_at')->nullable()->after('suspended_at');

            $table->index(['tenant_id', 'role', 'status']);
        });

        DB::table('users')
            ->whereNull('public_id')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                DB::table('users')->where('id', $user->id)->update([
                    'public_id' => (string) Str::ulid(),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'role', 'status']);
            $table->dropUnique(['public_id']);
            $table->dropUnique(['role_slot']);
            $table->dropColumn([
                'public_id',
                'tenant_id',
                'phone',
                'role',
                'status',
                'status_reason',
                'auth_version',
                'role_slot',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'suspended_at',
                'closed_at',
            ]);
        });
    }
};
