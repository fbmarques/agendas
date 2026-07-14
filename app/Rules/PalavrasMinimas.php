<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PalavrasMinimas implements ValidationRule
{
    public function __construct(private int $minimo = 10)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail("O campo :attribute deve conter texto.");
            return;
        }

        $palavras = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        $qtd = is_array($palavras) ? count($palavras) : 0;

        if ($qtd < $this->minimo) {
            $fail("O campo :attribute deve ter pelo menos {$this->minimo} palavras.");
        }
    }
}
