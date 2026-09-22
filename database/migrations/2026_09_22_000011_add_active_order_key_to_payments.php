<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('active_order_key', 64)->nullable()->after('order_id');
            $table->timestamp('last_inquired_at')->nullable()->after('terminal_at');
        });

        $duplicates = DB::table('payments')
            ->select('order_id')
            ->where('status', 'pending')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Tidak dapat menambahkan invariant payment aktif: ditemukan lebih dari satu attempt pending untuk satu order.');
        }

        DB::table('payments')->where('status', 'pending')->orderBy('id')->eachById(function (object $payment): void {
            DB::table('payments')->where('id', $payment->id)->update([
                'active_order_key' => 'order:'.$payment->order_id,
            ]);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique('active_order_key');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['active_order_key']);
            $table->dropColumn(['active_order_key', 'last_inquired_at']);
        });
    }
};
