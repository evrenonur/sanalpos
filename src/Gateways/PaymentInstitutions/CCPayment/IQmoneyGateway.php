<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\CCPayment;

class IQmoneyGateway extends CCPaymentAbstract
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
