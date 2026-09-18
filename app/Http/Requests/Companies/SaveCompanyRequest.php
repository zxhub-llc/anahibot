<?php

namespace App\Http\Requests\Companies;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        
        $team = $this->route('team');

        return [
            'ruc' => [
                'required', 'string', 'size:13', 'regex:/^[0-9]+$/',
                Rule::unique('companies', 'ruc')->ignore($team?->company?->id),
            ],
            'business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}