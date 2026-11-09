<?php

namespace App\Http\Requests\PostBlog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateBlog extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Cambia según tu lógica de permisos
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        /* $isPut = $this->isMethod('put');
        $required = $isPut ? 'required' : 'sometimes'; */
        $blogId = $this->route('blog');

        return [
            'titulo' => ['required', 'string', 'max:255'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'link' => ['required', 'string', 'max:255', Rule::unique('blogs', 'link')->ignore($blogId)],
            'subtitulo1' => ['required', 'string', 'max:255'],
            'subtitulo2' => ['required', 'string', 'max:500'],
            'video_url' => ['required', 'url', 'max:255'],
            'video_titulo' => ['required', 'string', 'max:255'],
            'created_at' => ['nullable', 'date'],

            // Campos opcionales: no se exige longitud mínima.
            'meta_titulo' => 'nullable|string|max:255',
            'meta_descripcion' => 'nullable|string|max:500',
            'popup_button_text' => 'nullable|string|max:50',
            'popup_button_color' => 'nullable|string|max:20',
            'popup_text_color' => 'nullable|string|max:20',

            'miniatura' => ['sometimes', 'image', 'max:3048'],
            'eliminar_miniatura' => ['sometimes', 'boolean'],
            'miniatura_nombre' => 'nullable|string|max:125',
            'miniatura_alt' => 'nullable|string|max:125',
            'miniatura_tittle' => 'nullable|string|max:125',
            'hero_image' => ['sometimes', 'image', 'max:3048'],
            'eliminar_hero_image' => ['sometimes', 'boolean'],
            'hero_image_nombre' => 'nullable|string|max:125',
            'hero_image_alt' => 'nullable|string|max:125',
            'hero_image_tittle' => 'nullable|string|max:125',
            'imagenes' => ['sometimes', 'array'],
            'imagenes.*' => ['sometimes', 'image', 'max:3048'],
            'imagen_tipo' => ['sometimes', 'array'],
            'imagen_tipo.*' => ['sometimes', 'in:file,existing'],
            'imagen_ids' => ['sometimes', 'array'],
            'imagen_ids.*' => ['sometimes', 'integer'],

            'img_alt' => ['nullable', 'array'],
            'img_alt.*' => ['nullable', 'string'],
            'img_nombre' => ['nullable', 'array'],
            'img_nombre.*' => ['nullable', 'string', 'max:125'],
            'img_tittle' => ['nullable', 'array'],
            'img_tittle.*' => ['nullable', 'string', 'max:125'],

            'parrafos' => ['required', 'array'],
            'parrafos.*' => ['required', 'string', 'max:65535'],
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
