<?php

namespace EvrenOnur\SanalPos\Gateways\Providers\CCPayment;

class HalkOdeGateway extends AbstractCCPaymentGateway
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
