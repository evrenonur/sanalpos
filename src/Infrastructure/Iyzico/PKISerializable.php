<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico;

/**
 * PKI Request String üretebilen nesneler için interface.
 */
interface PKISerializable
{
    public function toPKIRequestString(): string;
}
