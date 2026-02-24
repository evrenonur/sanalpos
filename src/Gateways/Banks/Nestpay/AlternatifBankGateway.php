<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class AlternatifBankGateway extends NestpayAbstractGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://sanalpos.abank.com.tr/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://sanalpos.abank.com.tr/fim/est3Dgate';
    }
}
