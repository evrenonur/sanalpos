<?php

namespace EvrenOnur\SanalPos\Infrastructures\Iyzico;

/**
 * PKI Request String üretebilen nesneler için interface.
 */
interface PKISerializable
{
    public function toPKIRequestString(): string;
}
