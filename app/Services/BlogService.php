<?php

namespace App\Services;

use App\Models\Blog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlogService
{
    private const RELACIONES = ['imagenes', 'parrafos', 'producto', 'etiqueta'];

    public function __construct(
        private BlogImageService $imageService,
    ) {}

    public function listar(): Collection
    {
        return Blog::with(self::RELACIONES)->get();
    }

    public function buscar(int $id): Blog
    {
        return Blog::with(self::RELACIONES)->findOrFail($id);
    }

    public function buscarPorLink(string $link): Blog
    {
        return Blog::with(self::RELACIONES)->where('link', $link)->firstOrFail();
    }

    public function paginados(int $perPage, int $page): LengthAwarePaginator
    {
        return Blog::paginate($perPage, ['*'], 'page', $page);
    }

    public function crear(array $datos, Request $request): Blog
    {
        return DB::transaction(function () use ($datos, $request) {
            if (!$request->hasFile('miniatura')) {
                throw new \Exception('No se recibió miniatura como archivo');
            }

            $blogData = [
                'titulo' => $datos['titulo'],
                'producto_id' => $datos['producto_id'] ?? null,
                'link' => $datos['link'],
                'subtitulo1' => $datos['subtitulo1'],
                'subtitulo2' => $datos['subtitulo2'],
                'video_url' => $datos['video_url'],
                'video_titulo' => $datos['video_titulo'],
                'miniatura' => $this->imageService->guardar($request->file('miniatura')),
                'miniatura_nombre' => $datos['miniatura_nombre'] ?? null,
                'miniatura_alt' => $datos['miniatura_alt'] ?? null,
                'miniatura_tittle' => $datos['miniatura_tittle'] ?? null,
                'hero_image' => $request->hasFile('hero_image')
                    ? $this->imageService->guardar($request->file('hero_image'))
                    : null,
                'hero_image_nombre' => $datos['hero_image_nombre'] ?? null,
                'hero_image_alt' => $datos['hero_image_alt'] ?? null,
                'hero_image_tittle' => $datos['hero_image_tittle'] ?? null,
            ];

            if (isset($datos['created_at'])) {
                $blogData['created_at'] = $datos['created_at'];
            }

            $blog = Blog::create($blogData);

            $this->crearEtiqueta($blog, $datos);
            $this->crearImagenes($blog, $datos, $request);
            $this->crearParrafos($blog, $datos['parrafos']);

            return $blog->fresh();
        });
    }

    public function actualizar(Blog $blog, array $datos, Request $request): Blog
    {
        return DB::transaction(function () use ($blog, $datos, $request) {
            $camposActualizar = [];

            foreach ([
                'titulo',
                'producto_id',
                'link',
                'subtitulo1',
                'subtitulo2',
                'video_url',
                'video_titulo',
                'miniatura_nombre',
                'miniatura_alt',
                'miniatura_tittle',
                'hero_image_nombre',
                'hero_image_alt',
                'hero_image_tittle',
                'created_at',
            ] as $campo) {
                if ($request->has($campo)) {
                    $camposActualizar[$campo] = $datos[$campo];
                }
            }

            if ($request->hasFile('miniatura')) {
                $this->imageService->borrar($blog->miniatura);
                $camposActualizar['miniatura'] = $this->imageService->guardar($request->file('miniatura'));
            } elseif ($request->boolean('eliminar_miniatura')) {
                $this->imageService->borrar($blog->miniatura);
                $camposActualizar['miniatura'] = null;
                $camposActualizar['miniatura_nombre'] = null;
                $camposActualizar['miniatura_alt'] = null;
                $camposActualizar['miniatura_tittle'] = null;
            }

            if ($request->hasFile('hero_image')) {
                $this->imageService->borrar($blog->hero_image);
                $camposActualizar['hero_image'] = $this->imageService->guardar($request->file('hero_image'));
            } elseif ($request->boolean('eliminar_hero_image')) {
                $this->imageService->borrar($blog->hero_image);
                $camposActualizar['hero_image'] = null;
                $camposActualizar['hero_image_nombre'] = null;
                $camposActualizar['hero_image_alt'] = null;
                $camposActualizar['hero_image_tittle'] = null;
            }

            $blog->update($camposActualizar);

            $this->sincronizarEtiqueta($blog, $datos);
            $this->sincronizarContenido($blog, $datos, $request);

            return $blog->fresh();
        });
    }

    public function eliminar(Blog $blog): void
    {
        DB::transaction(function () use ($blog) {
            $this->imageService->borrar($blog->miniatura);
            $this->imageService->borrar($blog->hero_image);

            foreach ($blog->imagenes as $imagen) {
                $this->imageService->borrar($imagen->ruta_imagen);
            }

            $blog->imagenes()->delete();
            $blog->parrafos()->delete();
            $blog->etiqueta()->delete();
            $blog->delete();
        });
    }

    private function crearEtiqueta(Blog $blog, array $datos): void
    {
        if (!$this->tieneDatosDeEtiqueta($datos)) {
            return;
        }

        $blog->etiqueta()->create($this->datosDeEtiqueta($datos));
    }

    private function sincronizarEtiqueta(Blog $blog, array $datos): void
    {
        if ($this->tieneDatosDeEtiqueta($datos)) {
            $blog->etiqueta()->updateOrCreate(
                ['blog_id' => $blog->id],
                $this->datosDeEtiqueta($datos)
            );

            return;
        }

        if ($blog->etiqueta) {
            $blog->etiqueta()->delete();
        }
    }

    private function tieneDatosDeEtiqueta(array $datos): bool
    {
        foreach ([
            'meta_titulo',
            'meta_descripcion',
            'popup_button_text',
            'popup_button_color',
            'popup_text_color',
            'popup_estilo',
            'popup3_sin_fondo',
        ] as $campo) {
            if (isset($datos[$campo])) {
                return true;
            }
        }

        return false;
    }

    private function datosDeEtiqueta(array $datos): array
    {
        return [
            'meta_titulo' => $datos['meta_titulo'] ?? null,
            'meta_descripcion' => $datos['meta_descripcion'] ?? null,
            'popup_button_text' => $datos['popup_button_text'] ?? null,
            'popup_button_color' => $datos['popup_button_color'] ?? null,
            'popup_text_color' => $datos['popup_text_color'] ?? null,
            'popup_estilo' => $datos['popup_estilo'] ?? null,
            'popup3_sin_fondo' => $datos['popup3_sin_fondo'] ?? null,
        ];
    }

    private function crearImagenes(Blog $blog, array $datos, Request $request): void
    {
        if (!isset($datos['imagenes'])) {
            return;
        }

        $altTexts = $datos['img_alt'] ?? [];
        $nombres = $datos['img_nombre'] ?? [];
        $titles = $datos['img_tittle'] ?? [];

        foreach ($request->file('imagenes', []) as $i => $imagen) {
            $blog->imagenes()->create([
                'ruta_imagen' => $this->imageService->guardar($imagen),
                'img_alt' => $altTexts[$i] ?? null,
                'img_nombre' => $nombres[$i] ?? null,
                'img_tittle' => $titles[$i] ?? null,
            ]);
        }
    }

    private function crearParrafos(Blog $blog, array $parrafos): void
    {
        $blog->parrafos()->createMany(
            array_map(fn ($parrafo) => ['parrafo' => $parrafo], $parrafos)
        );
    }

    /**
     * Reemplaza imágenes y párrafos del blog respetando el orden de secciones.
     */
    private function sincronizarContenido(Blog $blog, array $datos, Request $request): void
    {
        if (!$request->has('parrafos') && !$request->has('imagenes') && !$request->has('imagen_ids')) {
            return;
        }

        $tiposImagen = $request->input('imagen_tipo', []);
        if (is_string($tiposImagen)) {
            $tiposImagen = [$tiposImagen];
        }

        $imagenesNuevas = $request->file('imagenes', []);
        $idsImagenesExistentes = $request->input('imagen_ids', []);
        if (is_string($idsImagenesExistentes)) {
            $idsImagenesExistentes = [$idsImagenesExistentes];
        }

        $altTexts = $datos['img_alt'] ?? [];
        $nombres = $datos['img_nombre'] ?? [];
        $titles = $datos['img_tittle'] ?? [];
        $parrafos = $datos['parrafos'] ?? [];
        $imagenesActuales = $blog->imagenes->keyBy('id');

        $imagenesFinales = [];
        $idsConservados = [];
        $cursorArchivos = 0;
        $cursorIds = 0;

        foreach ($parrafos as $indice => $parrafo) {
            $tipo = $tiposImagen[$indice] ?? null;

            if ($tipo === 'file') {
                $archivo = $imagenesNuevas[$cursorArchivos] ?? null;
                $cursorArchivos++;

                if (!$archivo) {
                    throw new \Exception('No se encontró la imagen nueva esperada para una sección.');
                }

                $imagenesFinales[] = [
                    'ruta_imagen' => $this->imageService->guardar($archivo),
                    'img_alt' => $altTexts[$indice] ?? null,
                    'img_nombre' => $nombres[$indice] ?? null,
                    'img_tittle' => $titles[$indice] ?? null,
                ];
                continue;
            }

            $imagenId = $idsImagenesExistentes[$cursorIds] ?? null;
            $cursorIds++;

            if ($imagenId && isset($imagenesActuales[$imagenId])) {
                $imagenExistente = $imagenesActuales[$imagenId];
                $idsConservados[] = $imagenExistente->id;
                $imagenesFinales[] = [
                    'ruta_imagen' => $imagenExistente->ruta_imagen,
                    'img_alt' => empty($altTexts[$indice]) ? null : $altTexts[$indice],
                    'img_nombre' => empty($nombres[$indice]) ? null : $nombres[$indice],
                    'img_tittle' => empty($titles[$indice]) ? null : $titles[$indice],
                ];
                continue;
            }

            throw new \Exception('No se pudo resolver la imagen existente de una sección.');
        }

        foreach ($blog->imagenes as $imagen) {
            if (!in_array($imagen->id, $idsConservados)) {
                $this->imageService->borrar($imagen->ruta_imagen);
            }
        }

        $blog->imagenes()->delete();

        foreach ($imagenesFinales as $imagenData) {
            $blog->imagenes()->create($imagenData);
        }

        $blog->parrafos()->delete();
        $blog->parrafos()->createMany(
            array_map(fn ($parrafo) => ['parrafo' => $parrafo], $parrafos)
        );
    }
}
