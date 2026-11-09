<?php

namespace App\Http\Requests\PostBlog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PostStoreBlog extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        // Los límites coinciden con las columnas reales de la base de datos.
        return [
            'titulo' => 'required|string|max:255',
            'producto_id' => 'nullable|integer|exists:productos,id',
            'link' => 'required|string|max:255|unique:blogs,link',
            'subtitulo1' => 'required|string|max:255',
            'subtitulo2' => 'required|string|max:500',
            'video_url' => 'required|url|max:255',
            'video_titulo' => 'required|string|max:255',
            'created_at' => 'nullable|date',

            // Campos opcionales: no se exige longitud mínima.
            'meta_titulo' => 'nullable|string|max:255',
            'meta_descripcion' => 'nullable|string|max:500',

            'miniatura' => 'required|file|image|max:3048',
            'miniatura_nombre' => 'nullable|string|max:125',
            'miniatura_alt' => 'nullable|string|max:125',
            'miniatura_tittle' => 'nullable|string|max:125',

            'hero_image' => 'nullable|file|image|max:3048',
            'hero_image_nombre' => 'nullable|string|max:125',
            'hero_image_alt' => 'nullable|string|max:125',
            'hero_image_tittle' => 'nullable|string|max:125',

            'imagenes' => 'nullable|array',
            'imagenes.*' => 'required|image|max:3048',
            'img_alt' => 'nullable|array',
            'img_alt.*' => 'nullable|string',
            'img_nombre' => 'nullable|array',
            'img_nombre.*' => 'nullable|string|max:125',
            'img_tittle' => 'nullable|array',
            'img_tittle.*' => 'nullable|string|max:125',

            'parrafos' => 'required|array',
            'parrafos.*' => 'required|string|max:65535',
            'popup_button_text' => 'nullable|string|max:50',
            'popup_button_color' => 'nullable|string|max:20',
            'popup_text_color' => 'nullable|string|max:20',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
