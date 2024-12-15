<?php

namespace App\Rules\Triumph;

use App\Models\Triumph\Games;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TeamGameIdRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!Games::where(['game_id' => $value])->exists()) {
            $fail(__('messages.Game not found'));
        }
    }
}
