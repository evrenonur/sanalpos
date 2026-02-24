<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class IsBankasiGateway extends NestpayAbstractGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://sanalpos.isbank.com.tr/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://sanalpos.isbank.com.tr/fim/est3Dgate';
    }

    protected function getUrlAPITest(): string
    {
        return 'https://istest.asseco-see.com.tr/fim/api';
    }

    protected function getUrl3DTest(): string
    {
        return 'https://istest.asseco-see.com.tr/fim/est3Dgate';
    }
}
