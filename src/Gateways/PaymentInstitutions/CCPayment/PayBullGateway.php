<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\CCPayment;

class PayBullGateway extends CCPaymentAbstract
{
    protected function getTestBaseUrl(): string
    {
        return 'https://test.paybull.com/ccpayment';
    }

    protected function getLiveBaseUrl(): string
    {
        return 'https://app.paybull.com/ccpayment';
    }
}
