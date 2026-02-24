<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class AnadolubankGateway extends AbstractNestpayGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://anadolusanalpos.est.com.tr/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://anadolusanalpos.est.com.tr/fim/est3Dgate';
    }
}
