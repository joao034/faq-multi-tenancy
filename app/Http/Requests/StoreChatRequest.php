<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:15'],
            'from' => ['required', 'string', 'max:15'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'to.required' => 'El campo "to" (número del negocio) es requerido.',
            'from.required' => 'El campo "from" (número del cliente) es requerido.',
            'message.required' => 'El campo "message" es requerido.',
        ];
    }
}