<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AvailableNickname implements ValidationRule
{
    public function __construct(private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) return;
        $query = User::whereRaw('LOWER(nickname) = ?', [mb_strtolower(trim($value))]);
        if ($this->ignoreId !== null) $query->where('id', '!=', $this->ignoreId);
        if ($query->exists()) $fail('Ce surnom est déjà utilisé. Choisis-en un autre.');
    }
}
