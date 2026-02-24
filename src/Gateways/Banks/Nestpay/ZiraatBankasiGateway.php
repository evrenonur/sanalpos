<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

class ZiraatBankasiGateway extends AbstractNestpayGateway
{
    protected function getUrlAPILive(): string
    {
        return 'https://sanalpos2.ziraatbank.com.tr/fim/api';
    }

    protected function getUrl3DLive(): string
    {
        return 'https://sanalpos2.ziraatbank.com.tr/fim/est3Dgate';
    }

    protected function getUrlAPITest(): string
    {
        return 'https://torus-stage-ziraat.asseco-see.com.tr/fim/api';
    }

    protected function getUrl3DTest(): string
    {
        return 'https://torus-stage-ziraat.asseco-see.com.tr/fim/est3Dgate';
    }
}
