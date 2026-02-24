<?php

namespace EvrenOnur\SanalPos\Gateways\Providers\CCPayment;

class QNBPayGateway extends AbstractCCPaymentGateway
{
    protected function getTestBaseUrl(): string
    {
        return 'https://test.qnbpay.com.tr/ccpayment';
    }

    protected function getLiveBaseUrl(): string
    {
        return 'https://portal.qnbpay.com.tr/ccpayment';
    }
}
