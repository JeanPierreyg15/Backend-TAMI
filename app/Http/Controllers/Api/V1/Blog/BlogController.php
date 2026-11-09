<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostBlog\PostStoreBlog;
use App\Http\Requests\PostBlog\UpdateBlog;
use App\Services\ApiResponseService;
use App\Services\BlogService;
use App\Models\Blog;
use App\Http\Contains\HttpStatusCode;
use App\Http\Resources\BlogResource;
use Illuminate\Http\Request;

use App\Traits\SafeErrorTrait;

class BlogController extends Controller
{
    use SafeErrorTrait;
    protected ApiResponseService $apiResponse;

    public function __construct(
        ApiResponseService $apiResponse,
        private BlogService $blogService,
    ) {
        $this->apiResponse = $apiResponse;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/blogs",
     *     summary="Obtener lista de blogs",
     *     description="Devuelve todos los blogs con sus imágenes, párrafos, producto asociado y etiqueta.",
     *     tags={"Blogs"},
     *     @OA\Response(
     *         response=200,
     *         description="Blogs obtenidos exitosamente",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blogs obtenidos exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="titulo", type="string", example="Título del blog"),
     *                     @OA\Property(property="nombre_producto", type="string", nullable=true, example="Producto ejemplo"),
     *                     @OA\Property(property="link", type="string", example="https://midominio.com/blog/ejemplo"),
     *                     @OA\Property(property="subtitulo1", type="string", example="Subtítulo 1"),
     *                     @OA\Property(property="subtitulo2", type="string", example="Subtítulo 2"),
     *                     @OA\Property(property="video_id", type="string", example="abc123xyz"),
     *                     @OA\Property(property="video_url", type="string", example="https://youtube.com/watch?v=abc123xyz"),
     *                     @OA\Property(property="video_titulo", type="string", example="Título del video"),
     *                     @OA\Property(property="miniatura", type="string", example="https://midominio.com/imagenes/miniatura.jpg"),
     *                     @OA\Property(
     *                         property="imagenes",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="ruta_imagen", type="string", example="https://midominio.com/imagenes/img1.jpg"),
     *                             @OA\Property(property="text_alt", type="string", example="Texto alternativo")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="parrafos",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="parrafo", type="string", example="Este es un párrafo de ejemplo.")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="etiqueta",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="meta_titulo", type="string", example="Meta título ejemplo"),
     *                         @OA\Property(property="meta_descripcion", type="string", example="Descripción SEO del blog")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-13T15:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-13T15:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al obtener los blogs",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al obtener los blogs: mensaje de error")
     *         )
     *     )
     * )
     */


    public function index()
    {
        try {
            $blogs = $this->blogService->listar();

            return $this->apiResponse->successResponse(
                BlogResource::collection($blogs),
                'Blogs obtenidos exitosamente',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'obtener los blogs'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }


    /**
     * @OA\Post(
     *     path="/api/v1/blogs",
     *     summary="Crear un nuevo blog",
     *     tags={"Blogs"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="titulo",
     *                     type="string",
     *                     example="Cómo cuidar tu piel en verano"
     *                 ),
     *                 @OA\Property(
     *                     property="producto_id",
     *                     type="integer",
     *                     example=5
     *                 ),
     *                 @OA\Property(
     *                     property="link",
     *                     type="string",
     *                     example="como-cuidar-tu-piel-verano"
     *                 ),
     *                 @OA\Property(
     *                     property="subtitulo1",
     *                     type="string",
     *                     example="La importancia de la protección solar"
     *                 ),
     *                 @OA\Property(
     *                     property="subtitulo2",
     *                     type="string",
     *                     example="Consejos prácticos para tu rutina diaria"
     *                 ),
     *                 @OA\Property(
     *                     property="video_url",
     *                     type="string",
     *                     format="url",
     *                     example="https://www.youtube.com/watch?v=dQw4w9WgXcQ"
     *                 ),
     *                 @OA\Property(
     *                     property="video_titulo",
     *                     type="string",
     *                     example="Guía completa de protección solar"
     *                 ),
     *                 @OA\Property(
     *                     property="meta_titulo",
     *                     type="string",
     *                     example="Tips para cuidar la piel en verano"
     *                 ),
     *                 @OA\Property(
     *                     property="meta_descripcion",
     *                     type="string",
     *                     example="Aprende a proteger tu piel del sol con estos consejos prácticos y efectivos."
     *                 ),
     *                 @OA\Property(
     *                     property="miniatura",
     *                     type="string",
     *                     format="binary",
     *                     description="Imagen de portada del blog"
     *                 ),
     *                 @OA\Property(
     *                     property="imagenes[]",
     *                     description="Archivos de imágenes",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="text_alt[]",
     *                     description="Texto alternativo de cada imagen",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example="Persona aplicando protector solar"
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="parrafos[]",
     *                     description="Contenido de cada párrafo",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example="El uso de protector solar es fundamental para prevenir el envejecimiento prematuro."
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="created_at",
     *                     type="string",
     *                     format="date-time",
     *                     description="Fecha de creación manual (opcional)",
     *                     example="2025-08-13T15:00:00Z"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Blog creado con éxito"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function store(PostStoreBlog $request)
    {
        try {
            $blog = $this->blogService->crear($request->validated(), $request);

            return $this->apiResponse->successResponse(
                new BlogResource($blog),
                'Blog creado con éxito.',
                HttpStatusCode::CREATED
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'crear el blog'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Mostrar un blog específico
     *
     * @OA\Get(
     *     path="/api/v1/blogs/{id}",
     *     summary="Muestra un blog específico",
     *     description="Retorna los datos de un blog según su ID",
     *     operationId="showBlog",
     *     tags={"Blogs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del blog",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog encontrado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="producto_id", type="integer", example=1),
     *                 @OA\Property(property="titulo", type="string", example="Título del blog"),
     *                 @OA\Property(property="link", type="string", example="Link a blog..."),
     *                 @OA\Property(property="parrafo", type="string", example="Contenido del blog..."),
     *                 @OA\Property(property="descripcion", type="string", example="Descripcion del blog..."),
     *                 @OA\Property(property="miniatura", type="string", example="https://example.com/imagen-principal.jpg"),
     *                 @OA\Property(property="titulo_blog", type="string", example="Título del detalle del blog"),
     *                 @OA\Property(property="subtitulo_beneficio", type="string", example="Subtítulo de beneficios"),
     *                 @OA\Property(property="imagenes", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="url_imagen", type="string", example="https://example.com/imagen1.jpg"),
     *                         @OA\Property(property="parrafo_imagen", type="string", example="Descripción de la imagen")
     *                     )
     *                 ),
     *                 @OA\Property(property="url_video", type="string", example="https://example.com/video.mp4"),
     *                 @OA\Property(property="titulo_video", type="string", example="Título del video"),
     *                 @OA\Property(property="etiqueta", type="object", nullable=true,
     *                     @OA\Property(property="meta_titulo", type="string", example="Meta título del blog"),
     *                     @OA\Property(property="meta_descripcion", type="string", example="Meta descripción del blog")
     *                 ),
     *                 @OA\Property(property="created_at", type="string")
     *             ),
     *             @OA\Property(property="message", type="string", example="Blog encontrado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog no encontrado"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor"
     *     )
     * )
     */

    public function show(int $id)
    {
        try {
            $blog = $this->blogService->buscar($id);

            return $this->apiResponse->successResponse(
                new BlogResource($blog),
                'Blog obtenido exitosamente',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                'Error al obtener el blog: ' . $e->getMessage(),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mostrar un blog por su link
     *
     * @OA\Get(
     *     path="/api/v1/blogs/link/{link}",
     *     summary="Muestra un blog por su link",
     *     description="Retorna los datos de un blog, incluyendo detalles, imágenes y video, según su campo link",
     *     operationId="showBlogByLink",
     *     tags={"Blogs"},
     *     @OA\Parameter(
     *         name="link",
     *         in="path",
     *         description="Link único del blog",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog obtenido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="producto_id", type="integer", example=5),
     *                 @OA\Property(property="titulo", type="string", example="Título del blog"),
     *                 @OA\Property(property="link", type="string", example="mi-blog-unico"),
     *                 @OA\Property(property="parrafo", type="string", example="Contenido introductorio del blog."),
     *                 @OA\Property(property="descripcion", type="string", example="Descripción completa del blog."),
     *                 @OA\Property(property="imagenPrincipal", type="string", example="https://example.com/imagen-principal.jpg"),
     *                 @OA\Property(property="tituloBlog", type="string", example="Título del detalle del blog"),
     *                 @OA\Property(property="subTituloBlog", type="string", example="Subtítulo de beneficios"),
     *                 @OA\Property(property="imagenesBlog", type="array",
     *                     @OA\Items(type="string", example="https://example.com/imagen1.jpg")
     *                 ),
     *                 @OA\Property(property="parrafoImagenesBlog", type="array",
     *                     @OA\Items(type="string", example="Texto descriptivo de la imagen")
     *                 ),
     *                 @OA\Property(property="video_id", type="string", example="dQw4w9WgXcQ"),
     *                 @OA\Property(property="videoBlog", type="string", example="https://www.youtube.com/watch?v=dQw4w9WgXcQ"),
     *                 @OA\Property(property="tituloVideoBlog", type="string", example="Título del video"),
     *                 @OA\Property(property="etiqueta", type="object", nullable=true,
     *                     @OA\Property(property="meta_titulo", type="string", example="Meta título del blog"),
     *                     @OA\Property(property="meta_descripcion", type="string", example="Meta descripción del blog")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00Z")
     *             ),
     *             @OA\Property(property="message", type="string", example="Blog obtenido exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog no encontrado"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al obtener el blog"
     *     )
     * )
     */

    public function showLink(string $link)
    {
        try {
            $blog = $this->blogService->buscarPorLink($link);

            return $this->apiResponse->successResponse(
                new BlogResource($blog),
                'Blog obtenido exitosamente',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                'Error al obtener el blog: ' . $e->getMessage(),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/blogs/{id}",
     *     summary="Actualizar un blog (con archivos) usando method override",
     *     tags={"Blogs"},
     *     description="Usa POST con _method=PUT porque PHP no parsea multipart/form-data en PUT. Laravel hará el override a PUT internamente.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del blog a actualizar",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="_method",
     *                     type="string",
     *                     example="PUT",
     *                     description="Override de método para que Laravel trate la petición como PUT"
     *                 ),
     *                 @OA\Property(property="titulo", type="string", example="Cómo cuidar tu piel en invierno"),
     *                 @OA\Property(property="producto_id", type="integer", example=8),
     *                 @OA\Property(property="link", type="string", example="como-cuidar-tu-piel-invierno"),
     *                 @OA\Property(property="subtitulo1", type="string", example="Protección contra el frío extremo"),
     *                 @OA\Property(property="subtitulo2", type="string", example="Rutina de hidratación recomendada"),
     *                 @OA\Property(property="video_url", type="string", format="url", example="https://www.youtube.com/watch?v=abcd1234"),
     *                 @OA\Property(property="video_titulo", type="string", example="Guía completa de cuidado de la piel en invierno"),
     *                 @OA\Property(property="meta_titulo", type="string", example="Consejos para cuidar tu piel en invierno"),
     *                 @OA\Property(property="meta_descripcion", type="string", example="Descubre cómo proteger tu piel del frío y mantenerla saludable."),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-13T15:00:00Z", description="Fecha de creación manual (opcional)"),
     *
     *                 @OA\Property(
     *                     property="miniatura",
     *                     type="string",
     *                     format="binary",
     *                     description="Imagen de miniatura. No se puede enviar null en binary; usa eliminar_miniatura=1 para borrarla."
     *                 ),
     *                 @OA\Property(
     *                     property="eliminar_miniatura",
     *                     type="boolean",
     *                     example=false,
     *                     description="Envia true/1 para eliminar la miniatura existente si no subirás una nueva."
     *                 ),
     *
     *                 @OA\Property(
     *                     property="imagenes[]",
     *                     description="Imágenes del blog (reemplaza todas si se envía)",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *                 @OA\Property(
     *                     property="text_alt[]",
     *                     description="Texto alternativo alineado por índice con 'imagenes[]'",
     *                     type="array",
     *                     @OA\Items(type="string", example="Persona aplicando crema hidratante")
     *                 ),
     *                 @OA\Property(
     *                     property="parrafos[]",
     *                     description="Contenido de párrafos (reemplaza todos si se envía)",
     *                     type="array",
     *                     @OA\Items(type="string", example="Durante el invierno la piel tiende a resecarse, por lo que es esencial usar cremas nutritivas.")
     *                 )
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog actualizado exitosamente"
     *     ),
     *     @OA\Response(response=404, description="Blog no encontrado"),
     *     @OA\Response(response=422, description="Error de validación"),
     *     @OA\Response(response=500, description="Error interno del servidor")
     * )
     */
    public function update(UpdateBlog $request, $id)
    {
        $blog = Blog::findOrFail($id);

        try {
            $blog = $this->blogService->actualizar($blog, $request->validated(), $request);

            return $this->apiResponse->successResponse(
                new BlogResource($blog),
                'Blog actualizado exitosamente',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'actualizar el blog'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/blogs/{id}",
     *     summary="Eliminar un blog",
     *     tags={"Blogs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del blog a eliminar",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog eliminado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Blog eliminado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No se encontró el blog con el ID proporcionado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al intentar eliminar el blog",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al eliminar el blog: error inesperado")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $blog = Blog::findOrFail($id);

            $this->blogService->eliminar($blog);

            return $this->apiResponse->successResponse(
                null,
                'Blog eliminado exitosamente',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'eliminar el blog'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }

    public function paginate(Request $request)
    {
        $blogs = $this->blogService->paginados(
            $request->get('perPage', 5),
            $request->get('page', 1)
        );

        return response()->json([
            'data' => $blogs->items()
        ]);
    }
}
