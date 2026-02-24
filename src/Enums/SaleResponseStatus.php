<?php

namespace EvrenOnur\SanalPos\Enums;

enum SaleResponseStatus: int
{
    case Error = 0;
    case Success = 1;
    case RedirectURL = 2;
    case RedirectHTML = 3;
}
