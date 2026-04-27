<?php

namespace EvrenOnur\SanalPos\DTOs;

use EvrenOnur\SanalPos\Enums\InstallmentCommissionPolicy;

class MerchantAuth
{
    public function __construct(
        public string $bank_code = '',
        public string $merchant_id = '',
        public string $merchant_user = '',
        public string $merchant_password = '',
        public string $merchant_storekey = '',
        public bool $test_platform = true,
        public InstallmentCommissionPolicy $installment_commission_policy = InstallmentCommissionPolicy::Default,
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
            installment_commission_policy: isset($data['installment_commission_policy'])
                ? (is_int($data['installment_commission_policy'])
                    ? InstallmentCommissionPolicy::from($data['installment_commission_policy'])
                    : $data['installment_commission_policy'])
                : InstallmentCommissionPolicy::Default,
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
