<?php

namespace EvrenOnur\SanalPos\Enums;

enum SaleQueryResponseStatus: int
{
    case Error = 0;
    case Found = 1;
    case NotFound = 2;
}
