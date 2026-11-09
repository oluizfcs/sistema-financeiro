<?php

namespace App\Controllers\Services;

use Exception;

class Cpf
{
    public static function maskCpf(string $unmaskedCpf): string
    {
        if(strlen($unmaskedCpf) != 11) {
            throw new Exception("CPF inválido");
        }
        
        $chunks = str_split($unmaskedCpf, 3);

        return $chunks[0] . '.' . $chunks[1] . '.' . $chunks[2] . '-' . $chunks[3];
    }

    public static function unmaskCpf(string $maskedCpf): string
    {
        if(strlen($maskedCpf) != 14) {
            throw new Exception("CPF inválido");
        }

        return str_replace('-', '', str_replace('.', '', $maskedCpf));
    }
}