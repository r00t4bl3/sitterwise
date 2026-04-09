<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'type' => ['required', 'in:boolean,date,text,number,select'],
            'entity_type' => ['required', 'in:caregiver,client,both'],
            'options' => ['nullable', 'array'],
        ];
    }
}
