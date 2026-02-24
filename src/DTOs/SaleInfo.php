<?php

namespace EvrenOnur\SanalPos\DTOs;

use EvrenOnur\SanalPos\Enums\Currency;

class SaleInfo
{
    public function __construct(
        public string $card_name_surname = '',
        public string $card_number = '',
        public int $card_expiry_month = 0,
        public int $card_expiry_year = 0,
        public string $card_cvv = '',
        public ?Currency $currency = null,
        public float $amount = 0,
        public float $point = 0,
        public int $installment = 1,
        public string $campaign_code = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            card_name_surname: $data['card_name_surname'] ?? '',
            card_number: $data['card_number'] ?? '',
            card_expiry_month: (int) ($data['card_expiry_month'] ?? 0),
            card_expiry_year: (int) ($data['card_expiry_year'] ?? 0),
            card_cvv: $data['card_cvv'] ?? '',
            currency: isset($data['currency']) ? (is_int($data['currency']) ? Currency::from($data['currency']) : $data['currency']) : null,
            amount: (float) ($data['amount'] ?? 0),
            point: (float) ($data['point'] ?? 0),
            installment: (int) ($data['installment'] ?? 1),
            campaign_code: $data['campaign_code'] ?? '',
        );
    }

    /**
     * Doğrulama
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->card_name_surname)) {
            $errors[] = 'Kart üzerindeki isim boş olamaz.';
        }

        if (empty($this->card_number) || strlen($this->card_number) < 15 || strlen($this->card_number) > 19) {
            $errors[] = 'Kart numarası 15-19 karakter arasında olmalıdır.';
        }

        if ($this->card_expiry_month < 1 || $this->card_expiry_month > 12) {
            $errors[] = 'Son kullanma ayı 1-12 arasında olmalıdır.';
        }

        if ($this->card_expiry_year < 2019 || $this->card_expiry_year > 3100) {
            $errors[] = 'Son kullanma yılı geçersiz.';
        }

        if (empty($this->card_cvv) || strlen($this->card_cvv) < 3 || strlen($this->card_cvv) > 4) {
            $errors[] = 'CVV 3-4 karakter olmalıdır.';
        }

        if ($this->amount <= 0) {
            $errors[] = 'Tutar sıfırdan büyük olmalıdır.';
        }

        if ($this->installment < 1 || $this->installment > 15) {
            $errors[] = 'Taksit sayısı 1-15 arasında olmalıdır.';
        }

        return $errors;
    }
}
