<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\CCPayment;

class VeparaGateway extends CCPaymentAbstract
{
    protected function getTestBaseUrl(): string
    {
        return 'https://test.vepara.com.tr/ccpayment';
    }

    protected function getLiveBaseUrl(): string
    {
        return 'https://app.vepara.com.tr/ccpayment';
    }
}
