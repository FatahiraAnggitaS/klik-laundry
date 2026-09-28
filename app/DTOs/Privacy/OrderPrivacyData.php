<?php

namespace App\DTOs\Privacy;

final readonly class OrderPrivacyData
{
    /** @param list<array{type: string, contactName: string, contactPhone: string, address: string, area: string, city: string}> $addresses */
    public function __construct(
        public int $orderId,
        public int $customerId,
        public string $orderPublicId,
        public string $orderNumber,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone,
        public array $addresses,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(bool $revealed): array
    {
        return [
            'orderPublicId' => $this->orderPublicId,
            'orderNumber' => $this->orderNumber,
            'revealed' => $revealed,
            'customer' => [
                'name' => $revealed ? $this->customerName : $this->maskName($this->customerName),
                'email' => $revealed ? $this->customerEmail : $this->maskEmail($this->customerEmail),
                'phone' => $revealed ? $this->customerPhone : $this->maskPhone($this->customerPhone),
            ],
            'addresses' => array_map(fn (array $address): array => $revealed ? $address : [
                ...$address,
                'contactName' => $this->maskName($address['contactName']),
                'contactPhone' => $this->maskPhone($address['contactPhone']),
                'address' => '[Alamat disamarkan]',
            ], $this->addresses),
        ];
    }

    private function maskName(string $value): string
    {
        return $value === '' ? '***' : mb_substr($value, 0, 1).'***';
    }

    private function maskEmail(string $value): string
    {
        [$local, $domain] = array_pad(explode('@', $value, 2), 2, 'invalid');

        return $this->maskName($local).'@'.$domain;
    }

    private function maskPhone(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat('*', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
