<?php

namespace Ninja\Cartographer\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComplexStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'integer|min:18',
            'preferences' => 'array',
            'preferences.*.key' => 'required|string',
            'preferences.*.value' => 'required'
        ];
    }
}
