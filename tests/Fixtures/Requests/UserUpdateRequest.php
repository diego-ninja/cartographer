<?php

namespace Ninja\Cartographer\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $this->route('id')],
            'role' => ['sometimes', 'string', 'in:admin,user'],
            'metadata' => ['sometimes', 'array'],
            'metadata.preferences' => ['array'],
            'metadata.preferences.theme' => ['string', 'in:light,dark'],
            'metadata.preferences.notifications' => ['boolean'],
        ];
    }
}
