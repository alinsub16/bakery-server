<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('from') && $this->filled('to')) {
                $days = \Illuminate\Support\Carbon::parse($this->input('from'))
                    ->diffInDays(\Illuminate\Support\Carbon::parse($this->input('to')));

                if ($days > 90) {
                    $validator->errors()->add('to', 'The date range cannot exceed 90 days.');
                }
            }
        });
    }
}