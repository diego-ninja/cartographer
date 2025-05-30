<?php

namespace Ninja\Cartographer\Tests\Fixtures;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UppercaseRule implements ValidationRule
{
    public function __toString(): string
    {
        return 'uppercase';
    }
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (mb_strtoupper($value) !== $value) {
            $fail("The {$attribute} must be uppercase.");
        }
    }
}
