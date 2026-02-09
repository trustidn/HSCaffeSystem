<?php

namespace App\Enums;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Occupied => 'Terisi',
            self::Reserved => 'Dipesan',
            self::Maintenance => 'Maintenance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'emerald',
            self::Occupied => 'red',
            self::Reserved => 'amber',
            self::Maintenance => 'zinc',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Available => 'bg-emerald-50 border-emerald-200 dark:bg-emerald-950/30 dark:border-emerald-800',
            self::Occupied => 'bg-red-50 border-red-200 dark:bg-red-950/30 dark:border-red-800',
            self::Reserved => 'bg-amber-50 border-amber-200 dark:bg-amber-950/30 dark:border-amber-800',
            self::Maintenance => 'bg-zinc-100 border-zinc-300 dark:bg-zinc-800/50 dark:border-zinc-600',
        };
    }
}
