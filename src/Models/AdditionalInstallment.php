<?php

namespace EvrenOnur\SanalPos\Models;

class AdditionalInstallment
{
    public function __construct(
        public int $count = 0,
        public string $campaignCode = '',
        public string $campaignName = '',
        public string $campaignDescription = '',
        public bool $required = false,
    ) {}
}
