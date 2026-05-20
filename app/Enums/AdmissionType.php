<?php

namespace App\Enums;

enum AdmissionType: string
{
    case AdmitOne = 'admit_one';
    case AdmitTwo = 'admit_two';
    case AdmitFive = 'admit_five';
    case AdmitTen = 'admit_ten';
    case AdmitAll = 'admit_all';

    public function label(): string
    {
        return match ($this) {
            self::AdmitOne => 'Admit 1',
            self::AdmitTwo => 'Admit 2',
            self::AdmitFive => 'Admit 5',
            self::AdmitTen => 'Admit 10',
            self::AdmitAll => 'Admit All (Group)',
        };
    }

    public function admitCount(): int
    {
        return match ($this) {
            self::AdmitOne => 1,
            self::AdmitTwo => 2,
            self::AdmitFive => 5,
            self::AdmitTen => 10,
            self::AdmitAll => 999,
        };
    }
}
