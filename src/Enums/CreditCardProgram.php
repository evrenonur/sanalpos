<?php

namespace EvrenOnur\SanalPos\Enums;

enum CreditCardProgram: int
{
    case Unknown = -1;
    case Other = -2;
    case Axess = 0;
    case Bank24 = 1;
    case Bankkart = 2;
    case Bonus = 3;
    case CardFinans = 4;
    case Maximum = 5;
    case MilesAndSmiles = 6;
    case Neo = 7;
    case Paraf = 8;
    case ShopAndFly = 9;
    case Wings = 10;
    case World = 11;
    case Advantage = 12;
    case SaglamKart = 13;

    /**
     * Enum case adına göre eşleşme arar (case-insensitive).
     */
    public static function tryFromName(string $name): ?self
    {
        $normalized = mb_strtolower(trim($name));
        foreach (self::cases() as $case) {
            if (mb_strtolower($case->name) === $normalized) {
                return $case;
            }
        }

        return null;
    }
}
