<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\Payten;

class PaytenGateway extends PaytenAbstract
{
    protected function getApiTestUrl(): string
    {
        return 'https://entegrasyon.asseco-see.com.tr/msu/api/v2';
    }

    protected function getApiLiveUrl(): string
    {
        return 'https://merchantsafeunipay.com/msu/api/v2';
    }

    protected function get3DTestUrl(): string
    {
        return 'https://entegrasyon.asseco-see.com.tr/msu/api/v2/post/sale3d/{0}';
    }

    protected function get3DLiveUrl(): string
    {
        return 'https://merchantsafeunipay.com/msu/api/v2/post/sale3d/{0}';
    }

    protected function getBrandName(): string
    {
        return 'Payten';
    }
}
