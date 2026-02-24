<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class INGBankGateway extends NestpayAbstractGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://sanalpos.ingbank.com.tr/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://sanalpos.ingbank.com.tr/fim/est3Dgate';
    }
}
