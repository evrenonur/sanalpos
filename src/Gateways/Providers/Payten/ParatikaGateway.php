<?php

namespace EvrenOnur\SanalPos\Gateways\Providers\Payten;

class ParatikaGateway extends AbstractPaytenGateway
{
    protected function getApiTestUrl(): string
    {
        return 'https://entegrasyon.paratika.com.tr/paratika/api/v2';
    }

    protected function getApiLiveUrl(): string
    {
        return 'https://vpos.paratika.com.tr/paratika/api/v2';
    }

    protected function get3DTestUrl(): string
    {
        return 'https://entegrasyon.paratika.com.tr/paratika/api/v2/post/sale3d/{0}';
    }

    protected function get3DLiveUrl(): string
    {
        return 'https://vpos.paratika.com.tr/paratika/api/v2/post/sale3d/{0}';
    }

    protected function getBrandName(): string
    {
        return 'Paratika';
    }
}
