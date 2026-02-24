<?php

namespace EvrenOnur\SanalPos\Models;

class VirtualPOSAuth
{
    public function __construct(
        public string $bankCode = '',
        public string $merchantID = '',
        public string $merchantUser = '',
        public string $merchantPassword = '',
        public string $merchantStorekey = '',
        public bool $testPlatform = true,
    ) {}

    /**
     * Array'den oluştur
     */
    public static function fromArray(array $data): self
    {
        return new self(
            bankCode: $data['bankCode'] ?? '',
            merchantID: $data['merchantID'] ?? '',
            merchantUser: $data['merchantUser'] ?? '',
            merchantPassword: $data['merchantPassword'] ?? '',
            merchantStorekey: $data['merchantStorekey'] ?? '',
            testPlatform: $data['testPlatform'] ?? true,
        );
    }

    /**
     * Doğrulama
     */
    public function validate(): void
    {
        if (empty($this->bankCode)) {
            throw new \InvalidArgumentException('Banka kodu boş olamaz.');
        }
    }
}
