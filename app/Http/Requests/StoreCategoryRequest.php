<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role check already enforced by route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // Case-insensitive uniqueness: Postgres LOWER() comparison.
                Rule::unique('categories', 'name')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}