<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\Payten;

class VakifPaySGateway extends PaytenAbstract
{
    protected function getApiTestUrl(): string
    {
        return 'https://testpos.vakifpays.com.tr/vakifpays/api/v2';
    }

    protected function getApiLiveUrl(): string
    {
        return 'https://pos.vakifpays.com.tr/vakifpays/api/v2';
    }

    protected function get3DTestUrl(): string
    {
        return 'https://testpos.vakifpays.com.tr/vakifpays/api/v2/post/sale3d/{0}';
    }

    protected function get3DLiveUrl(): string
    {
        return 'https://pos.vakifpays.com.tr/vakifpays/api/v2/post/sale3d/{0}';
    }

    protected function getBrandName(): string
    {
        return 'VakıfPayS';
    }

    protected function getOnlineMetrixOrgId(): ?string
    {
        return '6bmm5c3v';
    }
}
