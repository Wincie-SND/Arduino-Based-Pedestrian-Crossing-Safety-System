<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreEnforcerRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => Str::lower($email)]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('addMember', $this->route('current_team'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
