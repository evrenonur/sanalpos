<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\CCPayment;

class HalkOdeGateway extends CCPaymentAbstract
{
    protected function getTestBaseUrl(): string
    {
        return 'https://testapp.halkode.com.tr/ccpayment';
    }

    protected function getLiveBaseUrl(): string
    {
        return 'https://app.halkode.com.tr/ccpayment';
    }
}
