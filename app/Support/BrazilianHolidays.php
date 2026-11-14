<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class BrazilianHolidays
{
    public static function forYear(int $year): array
    {
        $dates = [
            '01-01' => 'Confraternização Universal',
            '04-21' => 'Tiradentes',
            '05-01' => 'Dia do Trabalho',
            '09-07' => 'Independência do Brasil',
            '10-12' => 'Nossa Senhora Aparecida',
            '11-02' => 'Finados',
            '11-15' => 'Proclamação da República',
            '12-25' => 'Natal',
        ];

        if ($year >= 2024) {
            $dates['11-20'] = 'Dia Nacional de Zumbi e da Consciência Negra';
        }

        // The federal calendar includes Good Friday; use the Gregorian Easter date.
        $dates[Carbon::create($year, 3, 21)->addDays(easter_days($year, CAL_EASTER_ALWAYS_GREGORIAN) - 2)->format('m-d')] = 'Paixão de Cristo';

        return $dates;
    }
}
