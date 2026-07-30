<?php

namespace App\Http\Requests;

use App\Models\Bread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bread_id' => [
                'required',
                'integer',
                Rule::exists('breads', 'id')->where('is_active', true),
            ],
            'quantity_produced' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'bread_id.exists' => 'The selected bread does not exist or is not active.',
        ];
    }
}