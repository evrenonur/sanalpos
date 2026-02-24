<?php

namespace EvrenOnur\SanalPos\Enums;

enum SaleResponseStatu: int
{
    case Error = 0;
    case Success = 1;
    case RedirectURL = 2;
    case RedirectHTML = 3;
}
