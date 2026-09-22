<?php

namespace App\Console\Commands;

use App\Services\Payments\RunDuitkuSandboxSpikeService;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Throwable;

final class RunDuitkuSandboxSpikeCommand extends Command
{
    protected $signature = 'duitku:sandbox-spike
        {action : preflight, create, inquiry, atau callback}
        {--merchant-order-id= : Merchant order ID untuk inquiry}
        {--amount=10000 : Nominal integer rupiah untuk create}
        {--payment-method= : Kode channel sandbox yang telah diaktifkan merchant}
        {--payment-public-id= : Public ID payment paid untuk callback simulator}
        {--scenario= : Scenario callback duplicate atau out-of-order}';

    protected $description = 'Menjalankan probe opt-in terhadap Duitku sandbox tanpa memutasi domain application';

    public function handle(RunDuitkuSandboxSpikeService $service): int
    {
        try {
            return match ($this->argument('action')) {
                'preflight' => $this->preflight($service),
                'create' => $this->createInvoice($service),
                'inquiry' => $this->inquire($service),
                'callback' => $this->simulateCallback($service),
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

        $customerName = $this->secret('Nama test Customer (jangan gunakan data production)');
        $email = $this->secret('Email test Customer (jangan gunakan data production)');
        $result = $service->create(
            amount: (int) $this->option('amount'),
            paymentMethod: strtoupper((string) $paymentMethod),
            customerName: (string) $customerName,
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

        $expectedAmount = (int) $this->ask('Expected amount invoice');
        $expectedProviderReference = (string) $this->secret('Expected provider reference');
        $result = $service->inquire($merchantOrderId, $expectedAmount, $expectedProviderReference);
        $this->table(['Field', 'Safe value'], [
            ['Merchant order ID', $this->mask($result->merchantOrderId)],
            ['Provider reference', $this->mask($result->providerReference)],
            ['Status', $result->status],
            ['Fee available', $result->feeAmount === null ? 'no' : 'yes'],
            ['Latency', $result->latencyMilliseconds.' ms'],
        ]);

        return self::SUCCESS;
    }

    private function preflight(RunDuitkuSandboxSpikeService $service): int
    {
        $manifestPath = storage_path('app/private/milestone-0/evidence.json');
        if (! is_file($manifestPath)) {
            throw new RuntimeException('Evidence manifest private Milestone 0 belum tersedia.');
        }

        $contents = file_get_contents($manifestPath);
        if (! is_string($contents)) {
            throw new RuntimeException('Evidence manifest private Milestone 0 tidak dapat dibaca.');
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Evidence manifest private Milestone 0 bukan JSON valid.');
        }

        if (! is_array($manifest)) {
            throw new RuntimeException('Evidence manifest private Milestone 0 bukan object JSON.');
        }

        /** @var array<string, mixed> $manifest */
        $result = $service->preflight($manifest);
        $this->table(['Check', 'Safe value'], [
            ['Decisions approved/verified', (string) $result->decisionCount],
            ['Latest review date', $result->reviewedAt],
            ['Staging evidence reference', $result->stagingReference],
            ['Duitku environment', 'sandbox'],
            ['Production payment', 'disabled'],
            ['Callback', 'public HTTPS /webhooks/duitku'],
        ]);

        return self::SUCCESS;
    }

    private function simulateCallback(RunDuitkuSandboxSpikeService $service): int
    {
        $paymentPublicId = $this->option('payment-public-id');
        $scenario = $this->option('scenario');

        if (! is_string($paymentPublicId) || $paymentPublicId === '' || ! is_string($scenario) || $scenario === '') {
            $this->components->error('--payment-public-id dan --scenario wajib untuk action callback.');

            return self::INVALID;
        }

        $result = $service->simulateCallback($paymentPublicId, $scenario);
        $this->table(['Field', 'Safe value'], [
            ['Payment public ID', $this->mask($result->paymentPublicId)],
            ['Scenario', $result->scenario],
            ['Status before', $result->statusBefore],
            ['Status after', $result->statusAfter],
            ['Acknowledged requests', (string) $result->requestCount],
        ]);

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->components->error('Action harus preflight, create, inquiry, atau callback.');

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
            'Sandbox spike menolak production payment flag yang aktif.',
            'Sandbox spike menolak endpoint provider yang tidak diizinkan.',
            'Credential sandbox Duitku belum dikonfigurasi.',
            'Callback dan return URL sandbox wajib menggunakan HTTPS.',
            'Callback dan return URL sandbox wajib menggunakan HTTPS publik.',
            'Nominal sandbox harus lebih besar dari nol.',
            'Kode payment method harus terdiri dari dua karakter uppercase/alphanumeric.',
            'Nama Customer sandbox wajib diisi.',
            'Email test tidak valid.',
            'Merchant order ID tidak sesuai format sandbox Klik Laundry.',
            'Expected amount dan provider reference inquiry wajib diisi.',
            'Respons create invoice Duitku tidak memiliki kontrak yang diharapkan.',
            'Respons inquiry Duitku tidak memiliki kontrak yang diharapkan.',
            'Identitas merchant pada respons Duitku tidak cocok.',
            'Identitas inquiry Duitku tidak cocok dengan invoice yang diharapkan.',
            'Respons Duitku bukan JSON object yang valid.',
            'Fee Duitku bukan nominal integer rupiah yang dapat dinormalisasi.',
            'Payment sandbox belum memiliki provider reference tervalidasi.',
            'Callback sandbox tidak mendapat acknowledgement HTTP 200 yang valid.',
            'Scenario callback harus duplicate atau out-of-order.',
            'Payment sandbox tidak ditemukan.',
            'Callback simulator hanya menerima payment sandbox yang sudah paid.',
            'Callback simulator mendeteksi pelanggaran monotonic payment state.',
            'Evidence manifest private Milestone 0 belum tersedia.',
            'Evidence manifest private Milestone 0 tidak dapat dibaca.',
            'Evidence manifest private Milestone 0 bukan JSON valid.',
            'Evidence manifest private Milestone 0 bukan object JSON.',
        ];

        if (in_array($exception->getMessage(), $safeMessages, true)) {
            return $exception->getMessage();
        }

        if (str_starts_with($exception->getMessage(), 'Evidence manifest')) {
            return $exception->getMessage();
        }

        return 'Sandbox request gagal atau hasilnya tidak pasti. Jangan retry create secara buta; gunakan inquiry dengan merchant order ID yang sama.';
    }
}
