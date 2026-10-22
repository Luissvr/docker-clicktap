<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PageDescription implements ValidationRule
{
    public static function wordCount(string $description): int
    {
        return preg_match_all('/[\p{L}\p{N}]+(?:[\x{2019}\x{0027}-][\p{L}\p{N}]+)*/u', $description) ?: 0;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::wordCount($value) > 150) {
            $fail('La descripción no puede superar las 150 palabras.');
        }
    }
}
