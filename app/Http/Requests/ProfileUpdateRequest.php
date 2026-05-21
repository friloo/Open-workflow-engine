<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:64'],
            'email_notifications_enabled' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', Rule::in(array_keys(config('app.available_locales', ['de' => 'Deutsch'])))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email_notifications_enabled' => $this->boolean('email_notifications_enabled'),
        ]);
    }
}
