<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\CCPayment;

class ParolaparaGateway extends CCPaymentAbstract
{
    protected function getTestBaseUrl(): string
    {
        return 'https://testccpayment.parolapara.com/ccpayment';
    }

    protected function getLiveBaseUrl(): string
    {
        return 'https://ccpayment.parolapara.com/ccpayment';
    }
}
