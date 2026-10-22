<?php

namespace App\Http\Requests\Cliente;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sourceId = $this->input('source_id');
        $source = $sourceId ? \App\Models\ClienteSource::find($sourceId) : null;
        $isUpsertSource = $source && $source->name === 'Producto detalle';

        return [
            'name' => 'required|string|min:2|max:100',
            'email' => $isUpsertSource ? 
                'required|email|max:100' : 
                'required|email|unique:clientes,email|max:100',
            'celular' => $isUpsertSource ? [
                'required',
                'string',
                'regex:/^\+\d{1,3}\s?\d{1,15}(?:[-\s]?\d+)*$/'
            ] : [
                'required',
                'string',
                'unique:clientes,celular',
                'regex:/^\+\d{1,3}\s?\d{1,15}(?:[-\s]?\d+)*$/'
            ],
            'source_id' => 'required|exists:cliente_sources,id',
            'producto_id' => 'nullable|exists:productos,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no debe exceder los 100 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'email.unique' => 'Este correo ya está registrado.',
            'email.max' => 'El correo no debe exceder los 100 caracteres.',
            'celular.required' => 'El número de celular es obligatorio.',
            // 'celular.regex' => 'El celular debe tener exactamente 9 dígitos numéricos.'
            'celular.regex' => 'El formato del teléfono no es válido. Ejemplo: +51 999-999-999',
            'source_id.required' => 'La fuente de adquisición es obligatoria.',
            'source_id.exists' => 'La fuente de adquisición seleccionada no es válida.',
            'producto_id.exists' => 'El producto seleccionado no es válido.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors()
        ], 422));
    }
}
