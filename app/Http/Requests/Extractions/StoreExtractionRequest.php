<?php

namespace App\Http\Requests\Extractions;

use App\Enums\ExtractionTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_ruc' => ['required', 'string', 'size:13'],
            'trigger' => ['sometimes', new Enum(ExtractionTrigger::class)],
            'date_from' => ['required', 'date', 'before_or_equal:date_to'],
            'date_to' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'trigger' => $this->input('trigger', ExtractionTrigger::Manual->value),
        ]);
    }
}
