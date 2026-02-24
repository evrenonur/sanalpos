<?php

namespace EvrenOnur\SanalPos\Enums;

enum CreditCardBrand: int
{
    case Unknown = -1;
    case Visa = 0;
    case MasterCard = 1;
    case Troy = 2;
    case Amex = 3;
    case Discover = 4;
    case Unionpay = 5;
    case JCB = 6;
}
