<?php

namespace EvrenOnur\SanalPos\Gateways\Providers\CCPayment;

class IQmoneyGateway extends AbstractCCPaymentGateway
{
    protected function getTestBaseUrl(): string
    {
        return 'https://provisioning.iqmoneytr.com/ccpayment';
    }

    protected function getLiveBaseUrl(): string
    {
        return 'https://app.iqmoneytr.com/ccpayment';
    }
}
