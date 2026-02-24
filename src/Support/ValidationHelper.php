<?php

namespace EvrenOnur\SanalPos\Support;

use InvalidArgumentException;

class ValidationHelper
{
    /**
     * SaleRequest validasyonu
     */
    public static function validateSaleRequest(\EvrenOnur\SanalPos\DTOs\Requests\SaleRequest $request): void
    {
        if (empty($request->order_number)) {
            throw new InvalidArgumentException('order_number alanı zorunludur');
        }

        if (empty($request->customer_ip_address)) {
            throw new InvalidArgumentException('customer_ip_address alanı zorunludur');
        }

        if ($request->sale_info === null) {
            throw new InvalidArgumentException('sale_info alanı zorunludur');
        }

        if ($request->invoice_info === null) {
            throw new InvalidArgumentException('invoice_info alanı zorunludur');
        }

        if ($request->shipping_info === null) {
            throw new InvalidArgumentException('shipping_info alanı zorunludur');
        }

        self::validateSaleInfo($request->sale_info);
    }

    /**
     * SaleInfo validasyonu
     */
    public static function validateSaleInfo(\EvrenOnur\SanalPos\DTOs\SaleInfo $info): void
    {
        if (empty($info->card_name_surname)) {
            throw new InvalidArgumentException('card_name_surname alanı zorunludur');
        }

        $card_number = preg_replace('/\D/', '', $info->card_number);
        $cardLen = strlen($card_number);
        if ($cardLen < 15 || $cardLen > 19) {
            throw new InvalidArgumentException('card_number 15-19 karakter olmalıdır');
        }

        if ($info->card_expiry_month < 1 || $info->card_expiry_month > 12) {
            throw new InvalidArgumentException('card_expiry_month 1-12 arasında olmalıdır');
        }

        if ($info->card_expiry_year < 2019 || $info->card_expiry_year > 3100) {
            throw new InvalidArgumentException('card_expiry_year geçersiz');
        }

        $cvvLen = strlen($info->card_cvv);
        if ($cvvLen < 3 || $cvvLen > 4) {
            throw new InvalidArgumentException('card_cvv 3-4 karakter olmalıdır');
        }

        if ($info->amount <= 0) {
            throw new InvalidArgumentException('amount sıfırdan büyük olmalıdır');
        }

        if ($info->installment < 1 || $info->installment > 15) {
            throw new InvalidArgumentException('installment 1-15 arasında olmalıdır');
        }

        if (! StringHelper::isCardNumberValid($info->card_number)) {
            throw new InvalidArgumentException('Geçersiz kart numarası. Lütfen kart numaranızı kontrol ediniz.');
        }
    }

    /**
     * MerchantAuth validasyonu
     */
    public static function validateAuth(\EvrenOnur\SanalPos\DTOs\MerchantAuth $auth): void
    {
        if (empty($auth->bank_code)) {
            throw new InvalidArgumentException('bank_code alanı zorunludur');
        }
    }

    /**
     * CancelRequest validasyonu
     */
    public static function validateCancelRequest(\EvrenOnur\SanalPos\DTOs\Requests\CancelRequest $request): void
    {
        if (empty($request->order_number) && empty($request->transaction_id)) {
            throw new InvalidArgumentException('order_number veya transaction_id alanlarından en az biri zorunludur');
        }
    }

    /**
     * RefundRequest validasyonu
     */
    public static function validateRefundRequest(\EvrenOnur\SanalPos\DTOs\Requests\RefundRequest $request): void
    {
        if (empty($request->order_number) && empty($request->transaction_id)) {
            throw new InvalidArgumentException('order_number veya transaction_id alanlarından en az biri zorunludur');
        }

        if ($request->refund_amount <= 0) {
            throw new InvalidArgumentException('refund_amount sıfırdan büyük olmalıdır');
        }
    }

    /**
     * BINInstallmentQueryRequest validasyonu
     */
    public static function validateBINInstallmentQuery(\EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest $request): void
    {
        $binLen = strlen($request->BIN);
        if ($binLen < 6 || $binLen > 8) {
            throw new InvalidArgumentException('BIN 6-8 karakter olmalıdır');
        }
    }

    /**
     * CustomerInfo adres sanitizasyonu
     */
    public static function sanitizeCustomerInfo(\EvrenOnur\SanalPos\DTOs\CustomerInfo $info): \EvrenOnur\SanalPos\DTOs\CustomerInfo
    {
        return new \EvrenOnur\SanalPos\DTOs\CustomerInfo(
            name: StringHelper::maxLength(StringHelper::clearString($info->name), 50),
            surname: StringHelper::maxLength(StringHelper::clearString($info->surname), 50),
            email_address: StringHelper::maxLength(StringHelper::clearString($info->email_address), 100),
            phone_number: StringHelper::maxLength(StringHelper::clearString($info->phone_number), 20),
            tax_number: StringHelper::maxLength(StringHelper::clearString($info->tax_number), 20),
            tax_office: StringHelper::maxLength(StringHelper::clearString($info->tax_office), 50),
            country: $info->country,
            city_name: StringHelper::maxLength(StringHelper::clearString($info->city_name), 25),
            town_name: StringHelper::maxLength(StringHelper::clearString($info->town_name), 25),
            address_description: StringHelper::maxLength(StringHelper::clearString($info->address_description), 200),
            post_code: StringHelper::maxLength(StringHelper::clearString($info->post_code), 10),
        );
    }
}
