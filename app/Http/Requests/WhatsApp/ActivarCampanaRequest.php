<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ActivarCampanaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'producto_id' => 'required|exists:productos,id',
            'contenido_personalizado' => 'required',
            'imagen' => 'required|image|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la campaña es obligatorio',
            'nombre.string' => 'El nombre debe ser una cadena de texto',
            'nombre.max' => 'El nombre no puede exceder los 255 caracteres',
            'producto_id.required' => 'El producto es obligatorio',
            'producto_id.exists' => 'El producto seleccionado no existe',
            'contenido_personalizado.required' => 'El contenido del mensaje es obligatorio',
            'imagen.required' => 'La imagen de la campaña es obligatoria',
            'imagen.image' => 'El archivo debe ser una imagen válida (jpg, png, gif, etc.)',
            'imagen.max' => 'La imagen no puede superar los 2MB',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de campaña',
            'producto_id' => 'producto',
            'contenido_personalizado' => 'contenido del mensaje',
            'imagen' => 'imagen de campaña',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        $firstError = $errors->first();
        $fieldsWithErrors = array_keys($errors->toArray());

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $firstError,
            'fields_with_errors' => $fieldsWithErrors,
            'errors' => $errors->toArray()
        ], 422));
    }
}
