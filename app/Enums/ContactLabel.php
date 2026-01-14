<?php

namespace App\Enums;

enum ContactLabel: string
{
    case PAID = 'paid';
    case PROMISE = 'promise';
    case NO_ANSWER = 'no_answer';
    case WRONG_NUMBER = 'wrong_number';
    case REJECTED = 'rejected';
    case NEGOTIATING = 'negotiating';

    public function label(): string
    {
        return match ($this) {
            self::PAID => 'Pagó',
            self::PROMISE => 'Promesa de pago',
            self::NO_ANSWER => 'No contesta',
            self::WRONG_NUMBER => 'Número equivocado',
            self::REJECTED => 'Rechaza pago',
            self::NEGOTIATING => 'En negociación',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PAID => '#22c55e',
            self::PROMISE => '#f59e0b',
            self::NO_ANSWER => '#6b7280',
            self::WRONG_NUMBER => '#ef4444',
            self::REJECTED => '#dc2626',
            self::NEGOTIATING => '#3b82f6',
        };
    }
}
