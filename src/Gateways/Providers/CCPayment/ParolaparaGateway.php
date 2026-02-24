<?php

namespace EvrenOnur\SanalPos\Gateways\Providers\CCPayment;

class ParolaparaGateway extends AbstractCCPaymentGateway
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
