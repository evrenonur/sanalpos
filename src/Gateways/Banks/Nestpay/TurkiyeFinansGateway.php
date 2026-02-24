<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class TurkiyeFinansGateway extends AbstractNestpayGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://sanalpos.turkiyefinans.com.tr/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://sanalpos.turkiyefinans.com.tr/fim/est3Dgate';
    }
}
