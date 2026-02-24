<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\Currency;

class SaleInfo
{
    public function __construct(
        public string $cardNameSurname = '',
        public string $cardNumber = '',
        public int $cardExpiryDateMonth = 0,
        public int $cardExpiryDateYear = 0,
        public string $cardCVV = '',
        public ?Currency $currency = null,
        public float $amount = 0,
        public float $point = 0,
        public int $installment = 1,
        public string $campaignCode = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            cardNameSurname: $data['cardNameSurname'] ?? '',
            cardNumber: $data['cardNumber'] ?? '',
            cardExpiryDateMonth: (int) ($data['cardExpiryDateMonth'] ?? 0),
            cardExpiryDateYear: (int) ($data['cardExpiryDateYear'] ?? 0),
            cardCVV: $data['cardCVV'] ?? '',
            currency: isset($data['currency']) ? (is_int($data['currency']) ? Currency::from($data['currency']) : $data['currency']) : null,
            amount: (float) ($data['amount'] ?? 0),
            point: (float) ($data['point'] ?? 0),
            installment: (int) ($data['installment'] ?? 1),
            campaignCode: $data['campaignCode'] ?? '',
        );
    }

    /**
     * Doğrulama
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->cardNameSurname)) {
            $errors[] = 'Kart üzerindeki isim boş olamaz.';
        }

        if (empty($this->cardNumber) || strlen($this->cardNumber) < 15 || strlen($this->cardNumber) > 19) {
            $errors[] = 'Kart numarası 15-19 karakter arasında olmalıdır.';
        }

        if ($this->cardExpiryDateMonth < 1 || $this->cardExpiryDateMonth > 12) {
            $errors[] = 'Son kullanma ayı 1-12 arasında olmalıdır.';
        }

        if ($this->cardExpiryDateYear < 2019 || $this->cardExpiryDateYear > 3100) {
            $errors[] = 'Son kullanma yılı geçersiz.';
        }

        if (empty($this->cardCVV) || strlen($this->cardCVV) < 3 || strlen($this->cardCVV) > 4) {
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
