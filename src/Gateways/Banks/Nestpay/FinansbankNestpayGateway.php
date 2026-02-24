<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class FinansbankNestpayGateway extends AbstractNestpayGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://www.fbwebpos.com/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://www.fbwebpos.com/fim/est3Dgate';
    }
}
