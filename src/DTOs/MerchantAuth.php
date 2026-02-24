<?php

namespace EvrenOnur\SanalPos\DTOs;

class MerchantAuth
{
    public function __construct(
        public string $bank_code = '',
        public string $merchant_id = '',
        public string $merchant_user = '',
        public string $merchant_password = '',
        public string $merchant_storekey = '',
        public bool $test_platform = true,
    ) {}

    /**
     * Array'den oluştur
     */
    public static function fromArray(array $data): self
    {
        return new self(
            bank_code: $data['bank_code'] ?? '',
            merchant_id: $data['merchant_id'] ?? '',
            merchant_user: $data['merchant_user'] ?? '',
            merchant_password: $data['merchant_password'] ?? '',
            merchant_storekey: $data['merchant_storekey'] ?? '',
            test_platform: $data['test_platform'] ?? true,
        );
    }

    /**
     * Doğrulama
     */
    public function validate(): void
    {
        if (empty($this->bank_code)) {
            throw new \InvalidArgumentException('Banka kodu boş olamaz.');
        }
    }
}
