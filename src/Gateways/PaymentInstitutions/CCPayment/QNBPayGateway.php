<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\CCPayment;

class QNBPayGateway extends CCPaymentAbstract
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
