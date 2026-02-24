<?php

namespace EvrenOnur\SanalPos\Enums;

enum SaleQueryTransactionStatu: int
{
    case Paid = 1;
    case Refunded = 2;
    case PartialRefunded = 3;
    case Voided = 4;
}
