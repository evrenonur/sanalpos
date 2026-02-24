<?php

namespace EvrenOnur\SanalPos\Gateways\Providers\CCPayment;

class SipayGateway extends AbstractCCPaymentGateway
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
