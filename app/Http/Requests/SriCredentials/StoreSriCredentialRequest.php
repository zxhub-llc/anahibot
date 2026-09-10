<?php

namespace App\Http\Requests\SriCredentials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\SriEnvironment;

class StoreSriCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_ruc' => ['required', 'string', 'size:13'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => [$this->isMethod('put') ? 'sometimes' : 'required', 'string', 'min:4', 'max:255'],
            'environment' => ['sometimes', new Enum(SriEnvironment::class)],
        ];
    }
}
