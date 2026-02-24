<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class AkbankNestpayGateway extends AbstractNestpayGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://www.sanalakpos.com/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://www.sanalakpos.com/fim/est3Dgate';
    }
}
