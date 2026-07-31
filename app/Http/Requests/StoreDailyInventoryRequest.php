<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Intentionally NOT restricted to is_active=true here — a
            // discontinued bread can still have leftover stock to close out.
            'bread_id' => ['required', 'integer', Rule::exists('breads', 'id')],
            'closing_stock' => ['required', 'integer', 'min:0'],
        ];
    }
}