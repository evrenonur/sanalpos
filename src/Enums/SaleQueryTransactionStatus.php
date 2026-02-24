<?php

namespace EvrenOnur\SanalPos\Enums;

enum SaleQueryTransactionStatus: int
{
    case Paid = 1;
    case Refunded = 2;
    case PartialRefunded = 3;
    case Voided = 4;
}
