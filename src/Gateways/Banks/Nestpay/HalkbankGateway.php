<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class HalkbankGateway extends NestpayAbstractGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://sanalpos.halkbank.com.tr/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://sanalpos.halkbank.com.tr/fim/est3Dgate';
    }
}
