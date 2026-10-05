<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoWhatsappPaso;
use App\Models\ProductoEmailPaso;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class ProductoImageService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    /**
     * Maneja la creación o actualización de una imagen especial (popup, email, whatsapp).
     * Elimina la imagen anterior del mismo tipo si existe.
     *
     * @param Producto $producto Producto al que pertenece la imagen
     * @param UploadedFile|null $file Archivo de imagen
     * @param string $tipo Tipo de imagen: 'popup', 'email' o 'whatsapp'
     * @param string|null $altText Texto alternativo para SEO
     * @return ProductoImagen|null
     */
    public function handleSpecialImage(Producto $producto, ?UploadedFile $file, string $tipo, ?string $textValue = null, array $extraData = []): ?ProductoImagen
    {
        // WhatsApp y Email van a tablas normalizadas, no a producto_imagenes
        if ($tipo === 'whatsapp') {
            $this->handleWhatsappPasos($producto, $file, $extraData);
            return null;
        }

        if (str_starts_with($tipo, 'email')) {
            $slot = $extraData['slot_index'] ?? ($tipo === 'email' ? 1 : (int) filter_var($tipo, FILTER_SANITIZE_NUMBER_INT));
            $this->handleEmailPaso($producto, $file, $slot, $textValue, $extraData);
            return null;
        }

        // Popup y otros tipos siguen usando producto_imagenes
        $query = $producto->imagenes()->whereIn('tipo', $this->typeAliases($tipo));
        $imagenExistente = $query->first();
        $data = $extraData;

        if ($textValue !== null) {
            $data['texto_alt_SEO'] = \Illuminate\Support\Str::limit($textValue, 120);
        }

        if (!$file && empty($data)) {
            return $imagenExistente;
        }

        if ($file) {
            if ($imagenExistente) {
                $this->deleteExistingImageByType($producto, $tipo);
            }
            return $this->saveImage($producto, $file, $tipo, $data);
        } elseif ($imagenExistente) {
            $imagenExistente->update($data);
            return $imagenExistente;
        }

        return null;
    }

    /**
     * Maneja los 3 pasos de WhatsApp en tabla normalizada producto_whatsapp_pasos.
     */
   public function handleWhatsappPasos(Producto $producto, ?UploadedFile $mainFile, array $extraData = []): void
{
    $pasosConfig = [
        1 => ['mensaje_key' => 'whatsapp_mensaje',   'time_key' => 'whatsapp_time_1', 'image_file' => $mainFile],
        2 => ['mensaje_key' => 'whatsapp_mensaje_2', 'time_key' => 'whatsapp_time_2', 'image_file' => $extraData['whatsapp_image_2'] ?? null],
        3 => ['mensaje_key' => 'whatsapp_mensaje_3', 'time_key' => 'whatsapp_time_3', 'image_file' => $extraData['whatsapp_image_3'] ?? null],
    ];

    foreach ($pasosConfig as $paso => $config) {
        $pasoData = [];

        // Solo escribir si el campo llegó en el request y no es null
        if (array_key_exists($config['mensaje_key'], $extraData) && $extraData[$config['mensaje_key']] !== null) {
            $pasoData['mensaje'] = $extraData[$config['mensaje_key']];
        }

        if (array_key_exists($config['time_key'], $extraData) && $extraData[$config['time_key']] !== null) {
            $pasoData['delay_minutos'] = (int) $extraData[$config['time_key']];
        }

        $imageFile = $config['image_file'];
        if ($imageFile instanceof UploadedFile) {
            $existente = ProductoWhatsappPaso::where('producto_id', $producto->id)->where('paso', $paso)->first();
            if ($existente && !empty($existente->imagen_url)) {
                $this->deleteImageFromStorage($existente->imagen_url);
            }
            $pasoData['imagen_url'] = $this->guardarImagen($imageFile, $producto->nombre)['url'];
        }

        ProductoWhatsappPaso::updateOrCreate(
            ['producto_id' => $producto->id, 'paso' => $paso],
            $pasoData
        );
    }
}

    /**
     * Maneja un paso de Email en tabla normalizada producto_email_pasos.
     */
    public function handleEmailPaso(Producto $producto, ?UploadedFile $file, int $slot, ?string $subject, array $extraData = []): void
    {
        $pasoData = [
            'asunto' => $subject ?? '',
            'mensaje' => $extraData['email_mensaje'] ?? null,
            'btn_text' => $extraData['email_btn_text'] ?? null,
            'btn_link' => $extraData['email_btn_link'] ?? null,
            'btn_bg_color' => $extraData['email_btn_bg_color'] ?? null,
            'btn_text_color' => $extraData['email_btn_text_color'] ?? null,
            'delay_minutos' => (int) ($extraData['delay_minutes'] ?? 0),
        ];

        if ($file instanceof UploadedFile) {
            // Borrar imagen anterior si existe
            $existente = ProductoEmailPaso::where('producto_id', $producto->id)->where('paso', $slot)->first();
            if ($existente && !empty($existente->imagen_url)) {
                $this->deleteImageFromStorage($existente->imagen_url);
            }
            $pasoData['imagen_url'] = $this->guardarImagen($file, $producto->nombre)['url'];
        }

        ProductoEmailPaso::updateOrCreate(
            ['producto_id' => $producto->id, 'paso' => $slot],
            $pasoData
        );
    }

    /**
     * Guarda un archivo de imagen en storage/imagenes.
     *
     * @param UploadedFile $archivo Archivo a guardar
     * @return array{url: string, original_name: string} URL pública de la imagen (/storage/imagenes/nombre.ext) y nombre original
     */
    public function guardarImagen(UploadedFile $archivo, string $textoAlt = ''): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(
                "Extensión de archivo no permitida: {$extension}. Solo se permiten: " . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }

        $base = preg_replace('/[^A-Za-z0-9._\-]+/', '_', trim($textoAlt));
        if ($base === '' || $base === '.') {
            $base = 'imagen';
        }
        $nombre = $base . '_' . Str::random(8) . '.' . $extension;
        $archivo->storeAs("imagenes", $nombre, "public");
        return [
            'url' => "/storage/imagenes/" . $nombre,
            'original_name' => $archivo->getClientOriginalName(),
        ];
    }

    /**
     * Elimina todas las imágenes de galería de un producto.
     * No elimina imágenes especiales (popup, email, whatsapp).
     *
     * @param Producto $producto Producto cuyas imágenes de galería se eliminarán
     * @return void
     */
    public function deleteGalleryImages(Producto $producto): void
    {
        $imagenes = $producto->imagenes()
            ->where(fn($query) => $query->where('tipo', 'galeria')->orWhereNull('tipo'))
            ->get();

        foreach ($imagenes as $imagen) {
            $this->deleteImageFromStorage($imagen->url_imagen);
        }

        $producto->imagenes()
            ->where(fn($query) => $query->where('tipo', 'galeria')->orWhereNull('tipo'))
            ->delete();
    }

    /**
     * Guarda múltiples imágenes de galería.
     *
     * @param Producto $producto Producto al que se agregarán las imágenes
     * @param array $imagenes Array de UploadedFile
     * @param array $altTexts Array de textos alternativos (opcional)
     * @param array $titulos Array de títulos para cada imagen (opcional)
     * @return void
     */
    public function saveGalleryImages(Producto $producto, array $imagenes, array $altTexts = [], array $titulos = []): void
    {
        foreach ($imagenes as $i => $imagen) {
            $guardada = $this->guardarImagen($imagen, $altTexts[$i] ?? '');
            $producto->imagenes()->create([
                'url_imagen' => $guardada['url'],
                'original_name' => $guardada['original_name'],
                'texto_alt_SEO' => $altTexts[$i] ?? null,
                'titulo' => $titulos[$i] ?? null, // <--- Agregado
                'tipo' => 'galeria'
            ]);
        }
    }

    /**
     * Elimina todas las imágenes de un producto del storage.
     *
     * @param Producto $producto Producto cuyas imágenes se eliminarán
     * @return void
     */
    public function deleteAllImagesFromStorage(Producto $producto): void
    {
        foreach ($producto->imagenes as $imagen) {
            $this->deleteImageFromStorage($imagen->url_imagen);
        }
    }

    public function deleteExistingImageByType(Producto $producto, string $tipo): void
    {
        // WhatsApp: borrar de tabla normalizada
        if ($tipo === 'whatsapp') {
            $pasos = ProductoWhatsappPaso::where('producto_id', $producto->id)->get();
            foreach ($pasos as $paso) {
                if (!empty($paso->imagen_url)) {
                    $this->deleteImageFromStorage($paso->imagen_url);
                }
                $paso->delete();
            }
            return;
        }

        // Email: borrar de tabla normalizada
        if (str_starts_with($tipo, 'email')) {
            $slot = ($tipo === 'email' || $tipo === 'email1') ? 1 : (int) filter_var($tipo, FILTER_SANITIZE_NUMBER_INT);
            $paso = ProductoEmailPaso::where('producto_id', $producto->id)->where('paso', $slot)->first();
            if ($paso) {
                if (!empty($paso->imagen_url)) {
                    $this->deleteImageFromStorage($paso->imagen_url);
                }
                $paso->delete();
            }
            return;
        }

        // Popup y otros: borrar de producto_imagenes
        $query = $producto->imagenes()->whereIn('tipo', $this->typeAliases($tipo));

        $imagenAnterior = $query->first();

        if ($imagenAnterior) {
            $this->deleteImageFromStorage($imagenAnterior->url_imagen);
            $imagenAnterior->delete();
        }
    }

    private function saveImage(Producto $producto, UploadedFile $file, string $tipo, array $data): ProductoImagen
    {
        $guardada = $this->guardarImagen($file, $data['texto_alt_SEO'] ?? '');

        $payload = array_merge($data, [
            'url_imagen' => $guardada['url'],
            'original_name' => $guardada['original_name'],
            'tipo' => $tipo,
            // Asegurar que `texto_alt_SEO` y `titulo` tengan valores válidos
            'texto_alt_SEO' => $data['texto_alt_SEO'] ?? '',
            'titulo' => $data['titulo'] ?? null, // <--- Agregado
        ]);

        return $producto->imagenes()->create($payload);
    }

    public function deleteImageFromStorage(string $path): void
    {
        $path = str_replace('/storage/', '', $path);

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Renombra el archivo de una imagen en disco y actualiza la ruta en BD
     * basándose en el campo texto_alt_SEO del modelo.
     *
     * @return string|null Nueva URL de la imagen o null si no se pudo renombrar
     */
    public function renameImage(ProductoImagen $imagen): ?string
    {
        $currentPath = $imagen->url_imagen;
        $currentRelativePath = str_replace('/storage/', '', $currentPath);

        $extension = strtolower(pathinfo($currentRelativePath, PATHINFO_EXTENSION));

        // Si no hay texto_alt_SEO, usar el nombre original del archivo como base
        $textoAlt = $imagen->texto_alt_SEO;
        if (empty($textoAlt)) {
            $textoAlt = pathinfo($currentRelativePath, PATHINFO_FILENAME);
        }

        $base = preg_replace('/[^A-Za-z0-9._\-]+/', '_', trim($textoAlt));
        if ($base === '' || $base === '.') {
            return null;
        }

        $newNombre = $base . '_' . Str::random(6) . '.' . $extension;
        $newRelativePath = 'imagenes/' . $newNombre;

        if (!Storage::disk('public')->exists($currentRelativePath)) {
            return null;
        }

        if ($currentRelativePath === $newRelativePath) {
            return $currentPath;
        }

        Storage::disk('public')->move($currentRelativePath, $newRelativePath);

        $newUrl = '/storage/' . $newRelativePath;
        $imagen->update(['url_imagen' => $newUrl]);

        return $newUrl;
    }

    private function typeAliases(string $tipo): array
    {
        return match ($tipo) {
            'email1', 'email' => ['email1', 'email'],
            'popup_mobile' => ['popup_mobile', 'popup_mobile_image', 'popup_mobile_1', 'imageMobile'],
            'popup_mobile2' => ['popup_mobile2', 'popup_mobile_image2', 'popup_mobile_2', 'imageMobile2'],
            default => [$tipo],
        };
    }
}
