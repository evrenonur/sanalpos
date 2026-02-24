<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class TurkEkonomiBankasiGateway extends NestpayAbstractGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://sanalpos.teb.com.tr/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://sanalpos.teb.com.tr/fim/est3Dgate';
    }
}
