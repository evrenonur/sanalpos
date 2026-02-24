<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\CCPayment;

class SipayGateway extends CCPaymentAbstract
{
    protected function getTestBaseUrl(): string
    {
        return 'https://provisioning.sipay.com.tr/ccpayment';
    }

    protected function getLiveBaseUrl(): string
    {
        return 'https://app.sipay.com.tr/ccpayment';
    }

    /**
     * Sipay payment_status kontrolünü atlar — sadece status_code == 100 yeterli.
     */
    protected function skipPaymentStatusCheck(): bool
    {
        return true;
    }

    /**
     * Sipay "getpos_card_program" alan adını kullanır.
     */
    protected function getCardProgramFieldName(): string
    {
        return 'getpos_card_program';
    }
}
