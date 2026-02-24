<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class SekerbankGateway extends AbstractNestpayGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://sanalpos.sekerbank.com.tr/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://sanalpos.sekerbank.com.tr/fim/est3Dgate';
    }
}
