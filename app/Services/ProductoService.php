<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoWhatsappPaso;
use App\Models\ProductoEmailPaso;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Events\ProductoCreado;
use App\Events\ProductoActualizado;
use App\Events\ProductoEliminado;

class ProductoService
{
    public function __construct(
        private ProductoImageService $imageService,
        private KeywordProcessorService $keywordService
    ) {}

    public function listar(): Collection
    {
        return Producto::with(['imagenes', 'especificaciones', 'productosRelacionados.imagenes', 'etiqueta', 'dimensiones'])
            ->orderBy('created_at')
            ->get();
    }

    public function relacionados(int $id): Producto
    {
        return Producto::with(['productosRelacionados'])->findOrFail($id);
    }

    public function paginados(int $perPage, int $page): LengthAwarePaginator
    {
        return Producto::paginate($perPage, ['*'], 'page', $page);
    }

    public function buscar(string $id): ?Producto
    {
        return Producto::with(['imagenes', 'productosRelacionados', 'etiqueta', 'dimensiones'])->find($id);
    }

    public function buscarPorLink(string $link): ?Producto
    {
        return Producto::with(['imagenes', 'productosRelacionados.imagenes', 'etiqueta', 'dimensiones'])
            ->where('link', $link)
            ->first();
    }

    public function buscarOFallar(string $id): Producto
    {
        return Producto::findOrFail($id);
    }

/**
     * Crea un nuevo producto con todas sus relaciones.
     *
     * @param array $datosValidados Datos validados del producto
     * @param \Illuminate\Http\Request $request Request original con archivos
     * @return Producto
     * @throws \Exception
     */

    public function createProducto(array $datosValidados, Request $request): Producto
    {
        DB::beginTransaction();
        try {
            $producto = $this->createBaseProducto($datosValidados);

            if ($request->has('etiqueta')) {
                $this->createEtiqueta($producto, $datosValidados, $request);
            }

            $producto->productosRelacionados()->sync($datosValidados['relacionados'] ?? []);

            if (isset($datosValidados['imagenes'])) {
                $this->imageService->saveGalleryImages(
                    $producto,
                    $datosValidados['imagenes'],
                    $datosValidados['textos_alt'] ?? [],
                    $datosValidados['titulos'] ?? []
                );
            }

            $this->saveSpecialImages($producto, $request);
            $this->syncEspecificaciones($producto, $datosValidados['especificaciones'] ?? null);

            if (isset($datosValidados['dimensiones']) && is_array($datosValidados['dimensiones'])) {
                $this->createDimensiones($producto, $datosValidados['dimensiones']);
            }

            DB::commit();
            event(new ProductoCreado($producto));
            return $producto;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear producto: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

     /**
     * Actualiza un producto existente con todas sus relaciones.
     *
     * @param Producto $producto Instancia del producto a actualizar
     * @param array $datosValidados Datos validados
     * @param \Illuminate\Http\Request $request Request con archivos
     * @return Producto
     * @throws \Exception
     */
    public function updateProducto(Producto $producto, array $datosValidados, Request $request): Producto
    {
        DB::beginTransaction();
        try {

            $this->updateBaseProducto($producto, $datosValidados);

            if ($request->has('etiqueta')) {
                $this->updateEtiqueta($producto, $datosValidados, $request);
            }

            // ============================================
            // MANEJO INTELIGENTE DE IMÁGENES DE LA GALERÍA
            // ============================================

            $imagenesNuevas = $request->file('imagenes_nuevas', []);
            $imagenesExistentes = $request->input('imagenes_existentes', []);
            $imagenesEditadasDatos = $request->input('imagenes_editadas', []);
            $imagenesEditadasArchivos = $request->file('imagenes_editadas', []);

            $idsAConservar = [];

            foreach ($imagenesExistentes as $imgData) {
                if (!empty($imgData['id'])) {
                    $idsAConservar[] = $imgData['id'];
                } elseif (!empty($imgData['url'])) {
                    $match = $producto->imagenes()->where('url_imagen', $imgData['url'])->first();
                    if ($match) $idsAConservar[] = $match->id;
                }
            }

            foreach ($imagenesEditadasDatos as $editData) {
                if (!empty($editData['id'])) {
                    $idsAConservar[] = $editData['id'];
                }
            }

            $idsAConservar = array_unique($idsAConservar);

            $producto->imagenes()
                ->where(function ($q) {
                    $q->where('tipo', 'galeria')->orWhereNull('tipo');
                })
                ->whereNotIn('id', $idsAConservar)
                ->get()
                ->each(function ($img) {
                    $this->imageService->deleteImageFromStorage($img->url_imagen);
                    $img->delete();
                });

            if (!empty($imagenesEditadasDatos)) {
                foreach ($imagenesEditadasDatos as $index => $data) {
                    $archivoEditado = $imagenesEditadasArchivos[$index] ?? null;
                    $file = is_array($archivoEditado)
                        ? ($archivoEditado['file'] ?? null)
                        : $archivoEditado;

                    if ($file instanceof UploadedFile) {

                        $id = $data['id'];
                        $alt = $data['alt'] ?? '';
                        $ttl = $data['ttl'] ?? '';

                        $imagenDb = $producto->imagenes()->find($id);

                        if ($imagenDb) {

                            $this->imageService->deleteImageFromStorage($imagenDb->url_imagen);

                            $guardada = $this->imageService->guardarImagen($file, $alt);

                            $imagenDb->update([
                                'url_imagen' => $guardada['url'],
                                'original_name' => $guardada['original_name'],
                                'texto_alt_SEO' => $alt,
                                'titulo' => $ttl
                            ]);
                        }
                    }
                }
            }

            foreach ($imagenesExistentes as $imgData) {

                if (isset($imgData['id']) && isset($imgData['alt'])) {

                    $imagenExistente = $producto->imagenes()->find($imgData['id']);
                    if ($imagenExistente && $imagenExistente->texto_alt_SEO !== $imgData['alt']) {
                        $imagenExistente->update(['texto_alt_SEO' => $imgData['alt']]);
                        $this->imageService->renameImage($imagenExistente);
                    }
                }
                if (isset($imgData['id']) && isset($imgData['ttl'])) {

                    $producto->imagenes()
                        ->where('id', $imgData['id'])
                        ->update(['titulo' => $imgData['ttl']]);
                }
            }

            if (!empty($imagenesNuevas)) {

                $altTextsNuevos = $request->input('imagenes_nuevas_alt', []);
                $titulosNuevos = $request->input('imagenes_nuevas_titulo', []);

                foreach ($imagenesNuevas as $index => $file) {

                    $altText = $altTextsNuevos[$index] ?? "";
                    $titulo = $titulosNuevos[$index] ?? "";
                    $guardada = $this->imageService->guardarImagen($file, $altText);
                    $ruta = $guardada['url'];

                    $producto->imagenes()->create([
                        'url_imagen' => $ruta,
                        'original_name' => $guardada['original_name'],
                        'texto_alt_SEO' => $altText,
                        'titulo' => $titulo,
                        'tipo' => 'galeria'
                    ]);
                }
            }

            $this->saveSpecialImages($producto, $request);

            if (isset($datosValidados['especificaciones'])) {
                $producto->especificaciones()->delete();
                $this->syncEspecificaciones($producto, $datosValidados['especificaciones']);
            }

            if (isset($datosValidados['dimensiones'])) {
                $producto->dimensiones()->updateOrCreate(
                    ['id_producto' => $producto->id],
                    $datosValidados['dimensiones']
                );
            }

            if (isset($datosValidados['relacionados'])) {
                $producto->productosRelacionados()->sync($datosValidados['relacionados']);
            }

            DB::commit();

            event(new ProductoActualizado($producto));
            return $producto->fresh();
        } catch (\Exception $e) {

            DB::rollBack();
            throw $e;
        }
    }

    public function deleteProductoCompleto(Producto $producto): void
    {
        DB::beginTransaction();
        try {

            $this->imageService->deleteAllImagesFromStorage($producto);

            $producto->imagenes()->delete();
            $producto->especificaciones()->delete();
            $producto->etiqueta()->delete();
            $producto->dimensiones()->delete();
            $producto->productosRelacionados()->detach();

            // Borrar pasos normalizados de WhatsApp y Email
            $whatsappPasos = ProductoWhatsappPaso::where('producto_id', $producto->id)->get();
            foreach ($whatsappPasos as $paso) {
                if (!empty($paso->imagen_url)) {
                    $this->imageService->deleteImageFromStorage($paso->imagen_url);
                }
            }
            ProductoWhatsappPaso::where('producto_id', $producto->id)->delete();

            $emailPasos = ProductoEmailPaso::where('producto_id', $producto->id)->get();
            foreach ($emailPasos as $paso) {
                if (!empty($paso->imagen_url)) {
                    $this->imageService->deleteImageFromStorage($paso->imagen_url);
                }
            }
            ProductoEmailPaso::where('producto_id', $producto->id)->delete();

            $producto->delete();

            DB::commit();
            event(new ProductoEliminado($producto));
        } catch (\Exception $e) {

            DB::rollBack();
            Log::error("Error al eliminar producto {$producto->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function createBaseProducto(array $datos): Producto
    {
        return Producto::create([
            'nombre' => $datos['nombre'] ?? null,
            'porque_elegirnos' => $datos['porque_elegirnos'] ?? null,
            'link' => $datos['link'] ?? null,
            'titulo' => $datos['titulo'] ?? null,
            'detalle_titulo_tamano' => $datos['detalle_titulo_tamano'] ?? null,
            'detalle_titulo_color' => $datos['detalle_titulo_color'] ?? null,
            'detalle_titulo_estilo' => $datos['detalle_titulo_estilo'] ?? null,
            'subtitulo' => $datos['subtitulo'] ?? null,
            'stock' => $datos['stock'] ?? null,
            'precio' => $datos['precio'] ?? null,
            'seccion' => $datos['seccion'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'video_url' => $datos['video_url'] ?? null,
        ]);
    }

    private function updateBaseProducto(Producto $producto, array $datos): void
    {
        $camposPermitidos = [
            'nombre',
            'porque_elegirnos',
            'link',
            'titulo',
            'detalle_titulo_tamano',
            'detalle_titulo_color',
            'detalle_titulo_estilo',
            'subtitulo',
            'stock',
            'precio',
            'seccion',
            'descripcion',
            'video_url'
        ];

        $camposActualizar = array_filter(
            $datos,
            fn($key) => in_array($key, $camposPermitidos) && array_key_exists($key, $datos),
            ARRAY_FILTER_USE_KEY
        );

        if (!empty($camposActualizar)) {
            $producto->update($camposActualizar);
        }
    }

    private function createEtiqueta(Producto $producto, array $datos, $request): void
    {
        $keywords = $this->keywordService->processKeywordsFromJson($datos['keywords'] ?? null);

        $producto->etiqueta()->create([
            'meta_titulo' => $request->etiqueta['meta_titulo'] ?? null,
            'meta_descripcion' => $request->etiqueta['meta_descripcion'] ?? null,
            'keywords' => $keywords,
            'popup_estilo' => $request->etiqueta['popup_estilo'] ?? null,
            'popup3_sin_fondo' => filter_var(
                $request->etiqueta['popup3_sin_fondo'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ),
            // NUEVOS CAMPOS
            'popup_button_color' => $request->etiqueta['popup_button_color'] ?? null,
            'popup_text_color' => $request->etiqueta['popup_text_color'] ?? null,
            'popup_button_text' => $request->etiqueta['popup_button_text'] ?? null,
            'product_popup_delay_seconds' => $request->etiqueta['product_popup_delay_seconds'] ?? 30,
        ]);
    }

    private function updateEtiqueta(Producto $producto, array $datos, $request): void
    {
        $keywords = $this->keywordService->processKeywordsFromJson($datos['keywords'] ?? null);

        $producto->etiqueta()->updateOrCreate(
            ['producto_id' => $producto->id],
            [
                'meta_titulo' => $request->etiqueta['meta_titulo'] ?? null,
                'meta_descripcion' => $request->etiqueta['meta_descripcion'] ?? null,
                'keywords' => $keywords,
                'popup_estilo' => $request->etiqueta['popup_estilo'] ?? null,
                'popup3_sin_fondo' => filter_var(
                    $request->etiqueta['popup3_sin_fondo'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
                // NUEVOS CAMPOS
                'popup_button_color' => $request->etiqueta['popup_button_color'] ?? null,
                'popup_text_color' => $request->etiqueta['popup_text_color'] ?? null,
                'popup_button_text' => $request->etiqueta['popup_button_text'] ?? null,
                'product_popup_delay_seconds' => $request->etiqueta['product_popup_delay_seconds'] ?? 60,
            ]
        );
    }

    private function saveSpecialImages(Producto $producto, $request): void
    {
        $popupMobileImageCount = $this->normalizePopupMobileImageCount(
            $request->input('popup_mobile_image_count')
                ?? $request->input('popupMobileImageCount')
        );

        if ($this->firstUploadedFile($request, ['imagen_popup_mobile2', 'popup_mobile_image2', 'imageMobile2']) !== null) {
            $popupMobileImageCount = 2;
        }

        $tiposImagenes = [
            ['popup', 'imagen_popup', 'texto_alt_popup', []],
            ['popup2', 'imagen_popup2', 'texto_alt_popup2', []],
            ['popup_mobile', ['imagen_popup_mobile', 'popup_mobile_image', 'imageMobile'], 'texto_alt_popup_mobile', ['delete_imagen_popup_mobile', 'delete_popup_mobile', 'delete_imageMobile']],
            ['popup_mobile2', ['imagen_popup_mobile2', 'popup_mobile_image2', 'imageMobile2'], 'texto_alt_popup_mobile2', ['delete_imagen_popup_mobile2', 'delete_popup_mobile2', 'delete_imageMobile2']],
            ['whatsapp', 'imagen_whatsapp', 'texto_alt_whatsapp', [
                'whatsapp_mensaje' => $request->input('mensaje_whatsapp'),
                'whatsapp_mensaje_2' => $request->input('mensaje_whatsapp_2'),
                'whatsapp_mensaje_3' => $request->input('mensaje_whatsapp_3'),
                'whatsapp_time_1' => $request->input('whatsapp_time_1'),
                'whatsapp_time_2' => $request->input('whatsapp_time_2'),
                'whatsapp_time_3' => $request->input('whatsapp_time_3'),
                'whatsapp_image_2' => $request->file('imagen_whatsapp_2'),
                'whatsapp_image_3' => $request->file('imagen_whatsapp_3'),
            ]],
        ];

        foreach ($tiposImagenes as [$tipo, $imagenKeys, $textoKey, $payload]) {
            $imagenKeyList = is_array($imagenKeys) ? $imagenKeys : [$imagenKeys];

            if ($tipo === 'popup_mobile2' && $popupMobileImageCount === 1) {
                $this->imageService->deleteExistingImageByType($producto, $tipo);
                continue;
            }

            $extraData = [];
            $deleteKeys = [];

            if (array_is_list($payload)) {
                $deleteKeys = $payload;
            } elseif (is_array($payload)) {
                $extraData = $payload;
            }

            if ($this->shouldDeleteSpecialImage($request, $imagenKeyList, $deleteKeys, $tipo)) {
                $this->imageService->deleteExistingImageByType($producto, $tipo);

                if ($tipo === 'whatsapp') {
                    $this->imageService->handleSpecialImage($producto, null, $tipo, $request->input($textoKey), $extraData);
                }
                continue;
            }

            $this->imageService->handleSpecialImage(
                $producto,
                $this->firstUploadedFile($request, $imagenKeyList),
                $tipo,
                $request->input($textoKey),
                $extraData
            );
        }

        $this->saveEmailTemplates($producto, $request);
    }

    private function normalizePopupMobileImageCount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $count = (int) $value;

        return in_array($count, [1, 2], true) ? $count : null;
    }

    private function firstUploadedFile(Request $request, array $keys): ?UploadedFile
    {
        foreach ($keys as $key) {
            $file = $request->file($key);

            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }

    private function shouldDeleteSpecialImage(Request $request, array $imageKeys, array $deleteKeys, string $tipo): bool
    {
        $keysToCheck = array_merge($imageKeys, $deleteKeys, ["delete_$tipo", "delete_imagen_$tipo"]);

        foreach ($keysToCheck as $key) {
            if ($request->input($key) === '1') {
                return true;
            }
        }

        return false;
    }

    private function saveEmailTemplates(Producto $producto, $request): void
    {
        for ($slot = 1; $slot <= 3; $slot++) {
            $tipo = "email{$slot}";
            $imagenKey = "imagen_email_{$slot}";
            $subjectKey = "asunto_{$slot}";
            $messageKey = "mensaje_email_{$slot}";
            $btnTextKey = "email_btn_text_{$slot}";
            $btnLinkKey = "email_btn_link_{$slot}";
            $btnBgKey = "email_btn_bg_color_{$slot}";
            $btnTextColorKey = "email_btn_text_color_{$slot}";
            $delayKey = "email_time_{$slot}";
            $deleteKey = "delete_imagen_email_{$slot}";

            $file = $request->file($imagenKey);
            $deleteFlag = $request->input($deleteKey);

            if ($slot === 1 && !$request->has($imagenKey)) {
                $file = $request->file('imagen_email') ?? $file;
                $deleteFlag = $deleteFlag ?? $request->input('delete_imagen_email') ?? $request->input('delete_emailImage');
            }

            $extraData = ['slot_index' => $slot];

            if ($request->has($messageKey) || ($slot === 1 && $request->has('mensaje_email'))) {
                $extraData['email_mensaje'] = $request->input($messageKey) ?? $request->input('mensaje_email');
            }
            if ($request->has($btnTextKey) || ($slot === 1 && $request->has('email_btn_text'))) {
                $extraData['email_btn_text'] = $request->input($btnTextKey) ?? $request->input('email_btn_text');
            }
            if ($request->has($btnLinkKey) || ($slot === 1 && $request->has('email_btn_link'))) {
                $extraData['email_btn_link'] = $request->input($btnLinkKey) ?? $request->input('email_btn_link');
            }
            if ($request->has($btnBgKey) || ($slot === 1 && $request->has('email_btn_bg_color'))) {
                $extraData['email_btn_bg_color'] = $request->input($btnBgKey) ?? $request->input('email_btn_bg_color');
            }
            if ($request->has($btnTextColorKey) || ($slot === 1 && $request->has('email_btn_text_color'))) {
                $extraData['email_btn_text_color'] = $request->input($btnTextColorKey) ?? $request->input('email_btn_text_color');
            }
            if ($request->has($delayKey)) {
                $extraData['delay_minutes'] = (int) $request->input($delayKey);
            }

            $subject = $request->input($subjectKey) ?? ($slot === 1 ? $request->input('asunto') : null);

            // Borrar imagen del paso email si se pidió eliminar
            if ($deleteFlag === '1' && $file === null) {
                $existing = ProductoEmailPaso::where('producto_id', $producto->id)
                    ->where('paso', $slot)
                    ->first();

                if ($existing) {
                    if (!empty($existing->imagen_url)) {
                        $this->imageService->deleteImageFromStorage($existing->imagen_url);
                    }
                    $existing->update([
                        'imagen_url' => null,
                        'asunto' => $subject,
                        'mensaje' => $extraData['email_mensaje'] ?? $existing->mensaje,
                        'btn_text' => $extraData['email_btn_text'] ?? $existing->btn_text,
                        'btn_link' => $extraData['email_btn_link'] ?? $existing->btn_link,
                        'btn_bg_color' => $extraData['email_btn_bg_color'] ?? $existing->btn_bg_color,
                        'btn_text_color' => $extraData['email_btn_text_color'] ?? $existing->btn_text_color,
                        'delay_minutos' => (int) ($extraData['delay_minutes'] ?? $existing->delay_minutos),
                    ]);
                }

                continue;
            }

            $this->imageService->handleSpecialImage(
                $producto,
                $file,
                $tipo,
                $subject,
                $extraData
            );
        }
    }

    private function syncEspecificaciones(Producto $producto, ?string $especificacionesJson): void
    {
        if (!$especificacionesJson) return;

        $especificaciones = json_decode($especificacionesJson, true);

        if (!is_array($especificaciones)) return;

        foreach ($especificaciones as $valor) {
            $producto->especificaciones()->create(['valor' => $valor]);
        }
    }

    private function createDimensiones(Producto $producto, array $dimensiones): void
    {
        $producto->dimensiones()->create([
            'alto' => $dimensiones['alto'] ?? null,
            'largo' => $dimensiones['largo'] ?? null,
            'ancho' => $dimensiones['ancho'] ?? null,
        ]);
    }
}
