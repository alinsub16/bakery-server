<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role check happens in route middleware.
        // Same-day + no-closing-entry checks happen in the controller,
        // since they need the target record loaded first.
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_produced' => ['required', 'integer', 'min:1'],
        ];
    }
}