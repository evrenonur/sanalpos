<?php

namespace EvrenOnur\SanalPos\DTOs;

class AdditionalInstallment
{
    public function __construct(
        public int $count = 0,
        public string $campaign_code = '',
        public string $campaignName = '',
        public string $campaignDescription = '',
        public bool $required = false,
    ) {}
}
