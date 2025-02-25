<?php

namespace Ninja\Cartographer\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:admin,user'],
            'metadata' => ['sometimes', 'array'],
            'metadata.preferences' => ['array'],
            'metadata.preferences.theme' => ['string', 'in:light,dark'],
            'metadata.preferences.notifications' => ['boolean'],
        ];
    }
}
