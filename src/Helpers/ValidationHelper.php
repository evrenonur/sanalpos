<?php

namespace EvrenOnur\SanalPos\Helpers;

use InvalidArgumentException;

class ValidationHelper
{
    /**
     * SaleRequest validasyonu
     */
    public static function validateSaleRequest(\EvrenOnur\SanalPos\Models\SaleRequest $request): void
    {
        if (empty($request->orderNumber)) {
            throw new InvalidArgumentException('orderNumber alanı zorunludur');
        }

        if (empty($request->customerIPAddress)) {
            throw new InvalidArgumentException('customerIPAddress alanı zorunludur');
        }

        if ($request->saleInfo === null) {
            throw new InvalidArgumentException('saleInfo alanı zorunludur');
        }

        if ($request->invoiceInfo === null) {
            throw new InvalidArgumentException('invoiceInfo alanı zorunludur');
        }

        if ($request->shippingInfo === null) {
            throw new InvalidArgumentException('shippingInfo alanı zorunludur');
        }

        self::validateSaleInfo($request->saleInfo);
    }

    /**
     * SaleInfo validasyonu
     */
    public static function validateSaleInfo(\EvrenOnur\SanalPos\Models\SaleInfo $info): void
    {
        if (empty($info->cardNameSurname)) {
            throw new InvalidArgumentException('cardNameSurname alanı zorunludur');
        }

        $cardNumber = preg_replace('/\D/', '', $info->cardNumber);
        $cardLen = strlen($cardNumber);
        if ($cardLen < 15 || $cardLen > 19) {
            throw new InvalidArgumentException('cardNumber 15-19 karakter olmalıdır');
        }

        if ($info->cardExpiryDateMonth < 1 || $info->cardExpiryDateMonth > 12) {
            throw new InvalidArgumentException('cardExpiryDateMonth 1-12 arasında olmalıdır');
        }

        if ($info->cardExpiryDateYear < 2019 || $info->cardExpiryDateYear > 3100) {
            throw new InvalidArgumentException('cardExpiryDateYear geçersiz');
        }

        $cvvLen = strlen($info->cardCVV);
        if ($cvvLen < 3 || $cvvLen > 4) {
            throw new InvalidArgumentException('cardCVV 3-4 karakter olmalıdır');
        }

        if ($info->amount <= 0) {
            throw new InvalidArgumentException('amount sıfırdan büyük olmalıdır');
        }

        if ($info->installment < 1 || $info->installment > 15) {
            throw new InvalidArgumentException('installment 1-15 arasında olmalıdır');
        }

        if (! StringHelper::isCardNumberValid($info->cardNumber)) {
            throw new InvalidArgumentException('Geçersiz kart numarası. Lütfen kart numaranızı kontrol ediniz.');
        }
    }

    /**
     * VirtualPOSAuth validasyonu
     */
    public static function validateAuth(\EvrenOnur\SanalPos\Models\VirtualPOSAuth $auth): void
    {
        if (empty($auth->bankCode)) {
            throw new InvalidArgumentException('bankCode alanı zorunludur');
        }
    }

    /**
     * CancelRequest validasyonu
     */
    public static function validateCancelRequest(\EvrenOnur\SanalPos\Models\CancelRequest $request): void
    {
        if (empty($request->orderNumber) && empty($request->transactionId)) {
            throw new InvalidArgumentException('orderNumber veya transactionId alanlarından en az biri zorunludur');
        }
    }

    /**
     * RefundRequest validasyonu
     */
    public static function validateRefundRequest(\EvrenOnur\SanalPos\Models\RefundRequest $request): void
    {
        if (empty($request->orderNumber) && empty($request->transactionId)) {
            throw new InvalidArgumentException('orderNumber veya transactionId alanlarından en az biri zorunludur');
        }

        if ($request->refundAmount <= 0) {
            throw new InvalidArgumentException('refundAmount sıfırdan büyük olmalıdır');
        }
    }

    /**
     * BINInstallmentQueryRequest validasyonu
     */
    public static function validateBINInstallmentQuery(\EvrenOnur\SanalPos\Models\BINInstallmentQueryRequest $request): void
    {
        $binLen = strlen($request->BIN);
        if ($binLen < 6 || $binLen > 8) {
            throw new InvalidArgumentException('BIN 6-8 karakter olmalıdır');
        }
    }

    /**
     * CustomerInfo adres sanitizasyonu
     */
    public static function sanitizeCustomerInfo(\EvrenOnur\SanalPos\Models\CustomerInfo $info): \EvrenOnur\SanalPos\Models\CustomerInfo
    {
        return new \EvrenOnur\SanalPos\Models\CustomerInfo(
            name: StringHelper::maxLength(StringHelper::clearString($info->name), 50),
            surname: StringHelper::maxLength(StringHelper::clearString($info->surname), 50),
            emailAddress: StringHelper::maxLength(StringHelper::clearString($info->emailAddress), 100),
            phoneNumber: StringHelper::maxLength(StringHelper::clearString($info->phoneNumber), 20),
            taxNumber: StringHelper::maxLength(StringHelper::clearString($info->taxNumber), 20),
            taxOffice: StringHelper::maxLength(StringHelper::clearString($info->taxOffice), 50),
            country: $info->country,
            cityName: StringHelper::maxLength(StringHelper::clearString($info->cityName), 25),
            townName: StringHelper::maxLength(StringHelper::clearString($info->townName), 25),
            addressDesc: StringHelper::maxLength(StringHelper::clearString($info->addressDesc), 200),
            postCode: StringHelper::maxLength(StringHelper::clearString($info->postCode), 10),
        );
    }
}
