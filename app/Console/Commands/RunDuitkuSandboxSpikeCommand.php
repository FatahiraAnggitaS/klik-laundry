<?php

namespace App\Console\Commands;

use App\Services\Payments\RunDuitkuSandboxSpikeService;
use Illuminate\Console\Command;
use Throwable;

final class RunDuitkuSandboxSpikeCommand extends Command
{
    protected $signature = 'duitku:sandbox-spike
        {action : create atau inquiry}
        {--merchant-order-id= : Merchant order ID untuk inquiry}
        {--amount=10000 : Nominal integer rupiah untuk create}
        {--payment-method= : Kode channel sandbox yang telah diaktifkan merchant}';

    protected $description = 'Menjalankan probe opt-in terhadap Duitku sandbox tanpa memutasi domain application';

    public function handle(RunDuitkuSandboxSpikeService $service): int
    {
        try {
            return match ($this->argument('action')) {
                'create' => $this->createInvoice($service),
                'inquiry' => $this->inquire($service),
                default => $this->invalidAction(),
            };
        } catch (Throwable $exception) {
            $this->components->error($this->safeErrorMessage($exception));

            return self::FAILURE;
        }
    }

    private function createInvoice(RunDuitkuSandboxSpikeService $service): int
    {
        $paymentMethod = $this->option('payment-method');

        if (! is_string($paymentMethod) || $paymentMethod === '') {
            $paymentMethod = $this->ask('Kode payment method sandbox (contoh: NQ)');
        }

        $email = $this->ask('Email test Customer (jangan gunakan data production)');
        $result = $service->create(
            amount: (int) $this->option('amount'),
            paymentMethod: strtoupper((string) $paymentMethod),
            email: (string) $email,
        );

        $this->table(['Field', 'Safe value'], [
            ['Merchant order ID', $this->mask($result->merchantOrderId)],
            ['Provider reference', $this->mask($result->providerReference)],
            ['Status', $result->status],
            ['Latency', $result->latencyMilliseconds.' ms'],
            ['Expires at (UTC)', $result->expiresAt->format(DATE_ATOM)],
        ]);

        if ($this->confirm('Tampilkan payment URL hanya pada console ini?', false)) {
            $this->line($result->paymentUrl);
        }

        $this->components->warn('Live callback belum terbukti sampai URL HTTPS publik menerima callback Duitku yang valid.');

        return self::SUCCESS;
    }

    private function inquire(RunDuitkuSandboxSpikeService $service): int
    {
        $merchantOrderId = $this->option('merchant-order-id');

        if (! is_string($merchantOrderId) || $merchantOrderId === '') {
            $this->components->error('--merchant-order-id wajib untuk action inquiry.');

            return self::INVALID;
        }

        $result = $service->inquire($merchantOrderId);
        $this->table(['Field', 'Safe value'], [
            ['Merchant order ID', $this->mask($result->merchantOrderId)],
            ['Provider reference', $this->mask($result->providerReference)],
            ['Status', $result->status],
            ['Fee available', $result->feeAmount === null ? 'no' : 'yes'],
            ['Latency', $result->latencyMilliseconds.' ms'],
        ]);

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->components->error('Action harus create atau inquiry.');

        return self::INVALID;
    }

    private function mask(string $value): string
    {
        if (strlen($value) <= 8) {
            return '[MASKED]';
        }

        return substr($value, 0, 4).'…'.substr($value, -4);
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        $safeMessages = [
            'Sandbox spike tidak dapat dijalankan pada application environment production.',
            'Sandbox spike menolak environment selain sandbox.',
            'Sandbox spike menolak endpoint provider yang tidak diizinkan.',
            'Credential sandbox Duitku belum dikonfigurasi.',
            'Callback dan return URL sandbox wajib menggunakan HTTPS.',
            'Nominal sandbox harus lebih besar dari nol.',
            'Kode payment method harus terdiri dari dua karakter uppercase/alphanumeric.',
            'Email test tidak valid.',
            'Merchant order ID tidak sesuai format sandbox Klik Laundry.',
            'Respons create invoice Duitku tidak memiliki kontrak yang diharapkan.',
            'Respons inquiry Duitku tidak memiliki kontrak yang diharapkan.',
            'Respons Duitku bukan JSON object yang valid.',
            'Fee Duitku bukan nominal integer rupiah yang dapat dinormalisasi.',
        ];

        if (in_array($exception->getMessage(), $safeMessages, true)) {
            return $exception->getMessage();
        }

        return 'Sandbox request gagal atau hasilnya tidak pasti. Jangan retry create secara buta; gunakan inquiry dengan merchant order ID yang sama.';
    }
}
