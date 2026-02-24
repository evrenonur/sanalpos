<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\Payten;

class ZiraatPayGateway extends PaytenAbstract
{
    protected function getApiTestUrl(): string
    {
        return 'https://entegrasyon.ziraatpay.com.tr/ziraatpay/api/v2';
    }

    protected function getApiLiveUrl(): string
    {
        return 'https://vpos.ziraatpay.com.tr/ziraatpay/api/v2';
    }

    protected function get3DTestUrl(): string
    {
        return 'https://entegrasyon.ziraatpay.com.tr/ziraatpay/api/v2/post/sale3d/{0}';
    }

    protected function get3DLiveUrl(): string
    {
        return 'https://vpos.ziraatpay.com.tr/ziraatpay/api/v2/post/sale3d/{0}';
    }

    protected function getBrandName(): string
    {
        return 'ZiraatPay';
    }

    protected function getOnlineMetrixOrgId(): ?string
    {
        return '6bmm5c3v';
    }
}
