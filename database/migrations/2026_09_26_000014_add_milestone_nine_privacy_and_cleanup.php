<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable()->after('closed_at');
            $table->index('anonymized_at');
        });

        Schema::create('pii_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('actor_auth_version');
            $table->char('session_hash', 64);
            $table->text('reason');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();

            $table->index(['actor_id', 'order_id', 'expires_at'], 'pii_grant_actor_order_expiry_idx');
            $table->index(['expires_at', 'revoked_at'], 'pii_grant_expiry_idx');
        });

        Schema::create('pii_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grant_id')->nullable()->constrained('pii_access_grants')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('event', 24);
            $table->timestamp('occurred_at');

            $table->index(['actor_id', 'occurred_at']);
            $table->index(['order_id', 'occurred_at']);
            $table->index(['event', 'occurred_at']);
        });

        foreach (['delivery_tasks', 'weight_confirmations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('proof_deleted_at')->nullable()->after('proof_access_revoked_at');
                $table->unsignedInteger('proof_cleanup_attempts')->default(0)->after('proof_deleted_at');
                $table->timestamp('proof_cleanup_failed_at')->nullable()->after('proof_cleanup_attempts');
                $table->string('proof_cleanup_error', 120)->nullable()->after('proof_cleanup_failed_at');
            });
        }

        Schema::table('delivery_tasks', function (Blueprint $table): void {
            $table->index(['proof_expires_at', 'proof_deleted_at'], 'delivery_proof_cleanup_idx');
        });
        Schema::table('weight_confirmations', function (Blueprint $table): void {
            $table->index(['proof_expires_at', 'proof_deleted_at'], 'weight_proof_cleanup_idx');
        });
    }

    public function down(): void
    {
        $hasIrreversibleData = DB::table('users')->whereNotNull('anonymized_at')->exists()
            || DB::table('pii_access_grants')->exists()
            || DB::table('pii_access_logs')->exists()
            || DB::table('delivery_tasks')->whereNotNull('proof_deleted_at')->exists()
            || DB::table('weight_confirmations')->whereNotNull('proof_deleted_at')->exists();

        if ($hasIrreversibleData) {
            throw new RuntimeException('Milestone 9 privacy migration cannot be rolled back after irreversible privacy actions.');
        }

        Schema::table('weight_confirmations', function (Blueprint $table): void {
            $table->dropIndex('weight_proof_cleanup_idx');
            $table->dropColumn(['proof_deleted_at', 'proof_cleanup_attempts', 'proof_cleanup_failed_at', 'proof_cleanup_error']);
        });
        Schema::table('delivery_tasks', function (Blueprint $table): void {
            $table->dropIndex('delivery_proof_cleanup_idx');
            $table->dropColumn(['proof_deleted_at', 'proof_cleanup_attempts', 'proof_cleanup_failed_at', 'proof_cleanup_error']);
        });
        Schema::dropIfExists('pii_access_logs');
        Schema::dropIfExists('pii_access_grants');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['anonymized_at']);
            $table->dropColumn('anonymized_at');
        });
    }
};
