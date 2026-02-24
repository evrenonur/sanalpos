<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class CardplusGateway extends NestpayAbstractGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://sanalpos.card-plus.net/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://sanalpos.card-plus.net/fim/est3Dgate';
    }
}
