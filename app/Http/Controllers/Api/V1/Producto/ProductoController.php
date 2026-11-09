<?php

namespace App\Http\Controllers\Api\V1\Producto;

use App\Http\Controllers\Controller;
use App\Http\Requests\Producto\V2StoreProductoRequest;
use App\Http\Requests\Producto\V2UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Http\Contains\HttpStatusCode;
use App\Services\ProductoService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ProductoService $productoService
    ) {}
    /**
     * @OA\Get(
     *     path="/api/v1/productos",
     *     summary="Listar productos",
     *     description="Obtiene la lista de productos con sus imágenes, especificaciones, productos relacionados y etiquetas.",
     *     operationId="getProductos",
     *     tags={"Productos"},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de productos",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="titulo", type="string", example="Laptop Gamer"),
     *                 @OA\Property(property="nombre", type="string", example="Laptop XYZ"),
     *                 @OA\Property(property="link", type="string", example="/productos/laptop-xyz"),
     *                 @OA\Property(property="subtitulo", type="string", example="Potencia y velocidad"),
     *                 @OA\Property(property="stock", type="integer", example=12),
     *                 @OA\Property(property="precio", type="number", format="float", example=2499.99),
     *                 @OA\Property(property="seccion", type="string", example="Tecnología"),
     *                 @OA\Property(property="descripcion", type="string", example="Laptop de alto rendimiento para gaming."),
     *                 @OA\Property(
     *                     property="especificaciones",
     *                     type="object",
     *                     example={
     *                         "procesador": "Intel i7",
     *                         "ram": "16GB"
     *                     }
     *                 ),
     *                 @OA\Property(
     *                     property="imagenes",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="url_imagen", type="string", example="https://example.com/img1.jpg"),
     *                         @OA\Property(property="texto_alt_SEO", type="string", example="Imagen de laptop gamer")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="productos_relacionados",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="nombre", type="string", example="Mouse Gamer"),
     *                         @OA\Property(property="link", type="string", example="/productos/mouse-gamer"),
     *                         @OA\Property(property="titulo", type="string", example="Mouse RGB"),
     *                         @OA\Property(property="subtitulo", type="string", example="Alta precisión"),
     *                         @OA\Property(property="stock", type="integer", example=50),
     *                         @OA\Property(property="precio", type="number", format="float", example=49.99),
     *                         @OA\Property(property="seccion", type="string", example="Accesorios"),
     *                         @OA\Property(property="descripcion", type="string", example="Mouse gamer con retroiluminación RGB."),
     *                         @OA\Property(
     *                             property="imagenes",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="url_imagen", type="string", example="https://example.com/mouse1.jpg"),
     *                                 @OA\Property(property="texto_alt_SEO", type="string", example="Mouse gamer RGB")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="etiqueta",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="meta_titulo", type="string", example="Meta título del producto"),
     *                     @OA\Property(property="meta_descripcion", type="string", example="Meta descripción del producto")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-12T10:15:30Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-12T10:15:30Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al obtener los productos",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al obtener los productos: error de base de datos")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $productos = $this->productoService->listar();

            return ProductoResource::collection($productos)->resolve();
        } catch (\Exception $e) {
            return $this->handleException($e, 'obtener los productos');
        }
    }

    /**
     * Get all related products by id
     */
    public function related($id)
    {
        try {
            $producto = $this->productoService->relacionados((int) $id);

            return response()->json([
                'producto' => $producto->nombre,
                'relacionados' => $producto->productosRelacionados
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Producto');
        } catch (\Exception $e) {
            return $this->handleException($e, 'obtener productos relacionados');
        }
    }

    /**
     *
     * Permit only two params: `perPage` and `page`:
     *
     * - `perPage`: It is a range of products.
     * - `page`: The initial position of a specific group of products.
     */
    public function paginate(Request $request)
    {
        $productos = $this->productoService->paginados(
            $request->get('perPage', 5),
            $request->get('page', 1)
        );

        return response()->json([
            'data' => $productos->items()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/productos",
     *     summary="Crear un nuevo producto",
     *     description="Crea un nuevo producto con imágenes, etiquetas, productos relacionados y especificaciones (en formato JSON).",
     *     tags={"Productos"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"nombre", "precio", "imagenes[]"},
     *
     *                 @OA\Property(property="nombre", type="string", example="Camiseta deportiva"),
     *                 @OA\Property(property="link", type="string", example="camiseta-deportiva"),
     *                 @OA\Property(property="titulo", type="string", example="Camiseta DryFit Hombre"),
     *                 @OA\Property(property="subtitulo", type="string", example="Ideal para entrenamiento"),
     *                 @OA\Property(property="stock", type="integer", example=50),
     *                 @OA\Property(property="precio", type="number", format="float", example=89.90),
     *                 @OA\Property(property="seccion", type="string", example="Ropa Deportiva"),
     *                 @OA\Property(property="descripcion", type="string", example="Camiseta ligera y transpirable."),
     *
     *                 @OA\Property(
     *                     property="etiquetas[meta_titulo]",
     *                     type="string",
     *                     example="Camiseta deportiva hombre"
     *                 ),
     *                 @OA\Property(
     *                     property="etiquetas[meta_descripcion]",
     *                     type="string",
     *                     example="Compra la mejor camiseta deportiva para hombre."
     *                 ),
     *
     *                 @OA\Property(
     *                     property="relacionados[]",
     *                     type="array",
     *                     @OA\Items(type="integer", example=2)
     *                 ),
     *
     *                 @OA\Property(
     *                     property="imagenes[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="textos_alt[]",
     *                     type="array",
     *                     @OA\Items(type="string", example="Camiseta azul vista frontal")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="especificaciones",
     *                     type="string",
     *                     description="JSON con pares clave-valor de especificaciones",
     *                     example="{""Color"":""Azul"", ""Talla"":""M"", ""Material"":""Poliéster""}"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Producto insertado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Producto insertado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function store(v2StoreProductoRequest $request)
    {
        try {
            $datosValidados = $request->validated();

            $producto = $this->productoService->createProducto($datosValidados, $request);

            return $this->successResponse(
                new ProductoResource($producto->fresh(), true),
                'Producto insertado exitosamente',
                HttpStatusCode::CREATED->value
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'crear el producto', true);
        }
    }


    /**
     * Obtener un producto por su ID
     *
     * @OA\Get(
     *     path="/api/v1/productos/{id}",
     *     summary="Muestra un producto por su ID",
     *     description="Retorna los datos completos de un producto según su ID",
     *     operationId="getProductoByIdV2",
     *     tags={"Productos"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del producto",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Producto obtenido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nombreProducto", type="string", example="Producto A"),
     *                 @OA\Property(property="title", type="string", example="Producto Premium"),
     *                 @OA\Property(property="subtitulo", type="string", example="Calidad superior"),
     *                 @OA\Property(property="tagline", type="string", example="Innovación y excelencia"),
     *                 @OA\Property(property="descripcion", type="string", example="Este producto destaca por su..."),
     *                 @OA\Property(property="especificaciones", type="string"),
     *                 @OA\Property(property="dimensiones", type="object"),
     *                 @OA\Property(property="relatedProducts", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="images", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="url_imagen", type="string", format="url", example="/storage/imagenes/neon.jpg"),
     *                         @OA\Property(property="texto_alt_SEO", type="string", example="Neón rojo personalizado")
     *                     )
     *                 ),
     *                 @OA\Property(property="image", type="string", format="url", example="/storage/imagenes/principal.jpg"),
     *                 @OA\Property(property="stockProducto", type="integer", example=10),
     *                 @OA\Property(property="precioProducto", type="number", format="float", example=99.99),
     *                 @OA\Property(property="seccion", type="string", example="Electrónica"),
     *                 @OA\Property(property="etiqueta", type="object", nullable=true,
     *                     @OA\Property(property="meta_titulo", type="string", example="Meta título del producto"),
     *                     @OA\Property(property="meta_descripcion", type="string", example="Meta descripción del producto")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Producto encontrado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Producto no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Hubo un error en el servidor")
     *         )
     *     ),
     *     security={}
     * )
     */

    public function show(string $id)
    {
        try {
            $producto = $this->productoService->buscar($id);

            if ($producto === null) {
                return $this->notFound('Producto');
            }

            return $this->successResponse(
                new ProductoResource($producto, true),
                'Producto encontrado exitosamente',
                HttpStatusCode::OK->value
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'obtener el producto');
        }
    }

    /**
     * Obtener un producto por su enlace único
     *
     * @OA\Get(
     *     path="/api/v1/productos/link/{link}",
     *     summary="Muestra un producto usando su enlace único",
     *     description="Retorna los datos de un producto identificado por su campo 'link'",
     *     operationId="getProductoByLink",
     *     tags={"Productos"},
     *     @OA\Parameter(
     *         name="link",
     *         in="path",
     *         description="Enlace único del producto",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Operación exitosa",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nombreProducto", type="string", example="Producto A"),
     *                 @OA\Property(property="title", type="string", example="Producto Premium"),
     *                 @OA\Property(property="subtitulo", type="string", example="Calidad superior"),
     *                 @OA\Property(property="tagline", type="string", example="Innovación y excelencia"),
     *                 @OA\Property(property="descripcion", type="string", example="Este producto destaca por su..."),
     *                 @OA\Property(property="especificaciones", type="string"),
     *                 @OA\Property(property="dimensiones", type="object",
     *                     example={"alto":"10cm","ancho":"5cm"}
     *                 ),
     *                 @OA\Property(property="relatedProducts", type="array",
     *                     @OA\Items(type="integer"),
     *                     example={2,3,5}
     *                 ),
     *                 @OA\Property(property="images", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="url_imagen", type="string", format="url", example="/storage/imagenes/neon.jpg"),
     *                         @OA\Property(property="texto_alt_SEO", type="string", example="Neón rojo personalizado")
     *                     )
     *                 ),
     *                 @OA\Property(property="image", type="string", format="url", example="/storage/imagenes/principal.jpg"),
     *                 @OA\Property(property="stockProducto", type="integer", example=10),
     *                 @OA\Property(property="precioProducto", type="number", format="float", example=99.99),
     *                 @OA\Property(property="seccion", type="string", example="Electrónica"),
     *                 @OA\Property(property="etiqueta", type="object", nullable=true,
     *                     @OA\Property(property="meta_titulo", type="string", example="Meta título del producto"),
     *                     @OA\Property(property="meta_descripcion", type="string", example="Meta descripción del producto")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Producto encontrado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Hubo un error en el servidor")
     *         )
     *     ),
     *     security={}
     * )
     */

    public function showByLink($link)
    {
        try {
            $producto = $this->productoService->buscarPorLink($link);

            if ($producto === null) {
                return $this->notFound('Producto');
            }

            return $this->successResponse(
                new ProductoResource($producto, true),
                'Producto encontrado exitosamente',
                HttpStatusCode::OK->value
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'obtener el producto por link');
        }
    }

    /**
     * Actualizar un producto específico
     *
     * @OA\Post(
     *     path="/api/v1/productos/{id}",
     *     summary="Actualiza un producto específico (no funciona en Swagger)",
     *     description="Actualiza producto, elimina todas las antiguas imagenes y guarda las nuevas imagen en el servidor. Si lo intentas usar en Swagger no funcionará, pero si lo pruebas desde Postman si funciona. Los campos a enviar ya sea o desde Postman o desde un frontend son los mismos listados a continuación.",
     *     operationId="updateProducto2",
     *     tags={"Productos"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del producto a actualizar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={
     *                     "nombre", "titulo", "subtitulo", "stock", "precio",
     *                     "seccion", "descripcion", "especificaciones",
     *                      "imagenes", "textos_alt", "mensaje_correo", "_method"
     *                 },
     *                 @OA\Property(property="nombre", type="string", example="Selladora"),
     *                 @OA\Property(property="titulo", type="string", example="Titulo increíble"),
     *                 @OA\Property(property="subtitulo", type="string", example="Subtitulo increíble"),
     *                 @OA\Property(property="stock", type="integer", example=20),
     *                 @OA\Property(property="precio", type="number", format="float", example=100.50),
     *                 @OA\Property(property="seccion", type="string", example="Decoración"),
     *                 @OA\Property(property="descripcion", type="string", example="Descripción increíble"),
     *                 @OA\Property(property="especificaciones", type="string", example="Especificaciones increíbles"),
     *
     *                 @OA\Property(
     *                     property="imagenes",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                     description="Array de imágenes a subir"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="textos_alt",
     *                     type="array",
     *                     @OA\Items(type="string", example="Texto ALT para la imagen"),
     *                     description="Array de textos alternativos para las imágenes"
     *                 ),
     *
     *                 @OA\Property(property="mensaje_correo", type="string", example="Mensaje increíble"),
     *                 @OA\Property(property="_method", type="string", example="PUT"),
     *                 @OA\Property(property="meta_titulo", type="string", example="Meta título del producto"),
     *                 @OA\Property(property="meta_descripcion", type="string", example="Meta descripción del producto")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Producto actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Producto actualizado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor"
     *     )
     * )
     */
    public function update(V2UpdateProductoRequest $request, string $id)
    {
        Log::info('UPDATE Producto Request received', [
            'method' => $request->method(),
            'request_all' => $request->all(),
            'id' => $id
        ]);

        try {
            $producto = $this->productoService->buscarOFallar($id);
            $datosValidados = $request->validated();
            Log::info('Validated data:', ['datos_validados' => $datosValidados]);

            $productoActualizado = $this->productoService->updateProducto($producto, $datosValidados, $request);

            Log::info('Producto actualizado exitosamente', ['id' => $id]);

            return $this->successResponse(
                new ProductoResource($productoActualizado, true),
                'Producto actualizado exitosamente',
                HttpStatusCode::OK->value
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Producto');
        } catch (\Exception $e) {
            Log::error('Error actualizando producto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->handleException($e, 'actualizar el producto', true);
        }
    }

    /**
     * Eliminar un producto específico
     *
     * @OA\Delete(
     *     path="/api/v1/productos/{id}",
     *     summary="Elimina un producto específico",
     *     description="Elimina un producto existente según su ID",
     *     operationId="destroyProducto2",
     *     tags={"Productos"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del producto a eliminar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Producto eliminado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="null"),
     *             @OA\Property(property="message", type="string", example="Producto eliminado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor"
     *     )
     * )
     */
    public function destroy(string $id)
    {
        try {
            $producto = $this->productoService->buscarOFallar($id);
            $this->productoService->deleteProductoCompleto($producto);

            return $this->successMessage(
                'Producto eliminado exitosamente',
                HttpStatusCode::OK->value
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Producto');
        } catch (\Exception $e) {
            return $this->handleException($e, 'eliminar el producto', true);
        }
    }
}
