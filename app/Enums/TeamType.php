<?php

namespace App\Enums;

enum TeamType: string
{
    case Company = 'company';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Company => __('Empresa'),
            self::Accountant => __('Contador'),
        };
    }
}