<?php

namespace App\Support;

final class TransferBank
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'none' => 'Sin logo',
            'bancoestado' => 'BancoEstado',
            'banco-de-chile' => 'Banco de Chile',
            'santander' => 'Santander',
            'bci' => 'BCI',
            'itau' => 'Itaú',
            'scotiabank' => 'Scotiabank',
            'falabella' => 'Banco Falabella',
            'bice' => 'Banco BICE',
            'ripley' => 'Banco Ripley',
            'security' => 'Banco Security',
            'tenpo' => 'Tenpo',
            'mercado-pago' => 'Mercado Pago',
            'mercado-libre' => 'Mercado Libre',
        ];
    }

    public static function logo(?string $bank): ?string
    {
        $bank = trim((string) $bank);
        if ($bank === '' || $bank === 'none' || ! isset(self::options()[$bank])) {
            return null;
        }

        $styles = [
            'bancoestado' => ['BE', '#007a33'], 'banco-de-chile' => ['BC', '#00529b'],
            'santander' => ['S', '#ec0000'], 'bci' => ['BCI', '#0072ce'],
            'itau' => ['I', '#ec7000'], 'scotiabank' => ['S', '#d81e05'],
            'falabella' => ['F', '#36a852'], 'bice' => ['B', '#1b365d'],
            'ripley' => ['R', '#e30613'], 'security' => ['S', '#153f5f'],
            'tenpo' => ['T', '#6d2cff'], 'mercado-pago' => ['MP', '#009ee3'],
            'mercado-libre' => ['ML', '#ffe600'],
        ];
        [$initials, $color] = $styles[$bank];
        $label = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $textColor = $bank === 'mercado-libre' ? '#111827' : '#fff';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 56"><rect width="96" height="56" rx="10" fill="'.$color.'"/><text x="48" y="36" text-anchor="middle" font-family="Arial,sans-serif" font-size="'.($initials === 'BCI' ? '22' : '25').'" font-weight="700" fill="'.$textColor.'">'.$label.'</text></svg>';

        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
    }
}
