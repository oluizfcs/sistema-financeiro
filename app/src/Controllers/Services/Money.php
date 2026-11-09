<?php

namespace App\Controllers\Services;

class Money
{
    public static function centavos_para_reais(int|string|null $centavos): string
    {
        $centavos = intval($centavos);
        return number_format($centavos/100, 2, ',', '.');
    }

    public static function reais_para_centavos(string $reais): int
    {
        if(!(strlen($reais) > 0)) {
            return 0;
        }
        
        preg_match_all('/[0-9]/', $reais, $matches);

        $result = implode('', $matches[0]);
        return  $result != '' ? $result : 0;
    }
}