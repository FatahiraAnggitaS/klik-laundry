<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->string('dedupe_key')->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('processing_started_at')->nullable()->after('estimated_ready_at');
        });

        Schema::table('delivery_tasks', function (Blueprint $table): void {
            $table->timestamp('proof_expires_at')->nullable()->after('proof_size');
            $table->timestamp('proof_access_revoked_at')->nullable()->after('proof_expires_at');
            $table->index(['proof_expires_at', 'proof_access_revoked_at'], 'delivery_proof_retention_idx');
        });

        Schema::table('weight_confirmations', function (Blueprint $table): void {
            $table->timestamp('proof_expires_at')->nullable()->after('proof_size');
            $table->timestamp('proof_access_revoked_at')->nullable()->after('proof_expires_at');
            $table->index(['proof_expires_at', 'proof_access_revoked_at'], 'weight_proof_retention_idx');
        });

        DB::table('orders')
            ->where('fulfillment_status', 'processing')
            ->orderBy('id')
            ->eachById(function (object $order): void {
                $startedAt = DB::table('order_status_histories')
                    ->where('order_id', $order->id)
                    ->where('to_status', 'processing')
                    ->oldest('occurred_at')
                    ->value('occurred_at') ?? $order->updated_at;
                $duration = (int) (DB::table('order_items')->where('order_id', $order->id)->value('estimated_duration_minutes') ?? 0);

                DB::table('orders')->where('id', $order->id)->update([
                    'processing_started_at' => $startedAt,
                    'estimated_ready_at' => $order->estimated_ready_at ?? CarbonImmutable::parse($startedAt)->addMinutes($duration),
                ]);
            });

        DB::table('orders')
            ->whereNotNull('completed_at')
            ->orderBy('id')
            ->eachById(function (object $order): void {
                $expiresAt = CarbonImmutable::parse($order->completed_at)->addDays(90);
                DB::table('delivery_tasks')->where('order_id', $order->id)->whereNotNull('proof_key')->update(['proof_expires_at' => $expiresAt]);
                DB::table('weight_confirmations')->where('order_id', $order->id)->whereNotNull('proof_key')->update(['proof_expires_at' => $expiresAt]);
            });
    }

    public function down(): void
    {
        Schema::table('weight_confirmations', function (Blueprint $table): void {
            $table->dropIndex('weight_proof_retention_idx');
            $table->dropColumn(['proof_expires_at', 'proof_access_revoked_at']);
        });
        Schema::table('delivery_tasks', function (Blueprint $table): void {
            $table->dropIndex('delivery_proof_retention_idx');
            $table->dropColumn(['proof_expires_at', 'proof_access_revoked_at']);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('processing_started_at'));
        Schema::dropIfExists('notifications');
    }
};
