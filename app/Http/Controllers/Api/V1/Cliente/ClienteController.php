<?php

namespace App\Http\Controllers\Api\V1\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Cliente;
use App\Services\ApiResponseService;
use App\Services\ClienteService;
use App\Http\Contains\HttpStatusCode;
use Illuminate\Http\Request;
use App\Traits\SafeErrorTrait;

class ClienteController extends Controller
{
    use SafeErrorTrait;
    protected ApiResponseService $apiResponse;

    public function __construct(
        ApiResponseService $apiResponse,
        private ClienteService $clienteService,
    ) {
        $this->apiResponse = $apiResponse;
    }


    /**
     * @OA\Post(
     *     path="/api/v1/clientes",
     *     summary="Crear un nuevo cliente",
     *     description="Crea un nuevo cliente con los datos proporcionados.",
     *     operationId="createCliente",
     *     tags={"Clientes"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del cliente a registrar",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"name","email","celular"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="Juan Pérez"
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 example="juan.perez@example.com"
     *             ),
     *             @OA\Property(
     *                 property="celular",
     *                 type="string",
     *                 example="987654321"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Cliente creado con éxito",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Cliente creado con éxito."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Juan Pérez"),
     *                 @OA\Property(property="email", type="string", example="juan.perez@example.com"),
     *                 @OA\Property(property="celular", type="string", example="987654321")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Error al crear el cliente: mensaje del error")
     *         )
     *     )
     * )
     */

    public function store(StoreClienteRequest $request)
    {
        try {
            $resultado = $this->clienteService->registrar($request->validated());

            return $this->apiResponse->successResponse(
                $resultado['cliente'],
                $resultado['actualizado'] ? 'Cliente actualizado con éxito.' : 'Cliente creado con éxito',
                $resultado['actualizado'] ? HttpStatusCode::OK : HttpStatusCode::CREATED
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'Crear el cliente'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clientes/{id}",
     *     summary="Obtener un cliente",
     *     description="Obtiene la información de un cliente específico por su ID.",
     *     operationId="getClienteById",
     *     tags={"Clientes"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del cliente a obtener",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cliente obtenido exitosamente",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Cliente obtenido exitosamente"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     example=1
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     example="Juan Pérez"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     example="juan.perez@example.com"
     *                 ),
     *                 @OA\Property(
     *                     property="celular",
     *                     type="string",
     *                     example="+51 987654321"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cliente no encontrado",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Cliente no encontrado"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Error al obtener el cliente: mensaje del error"
     *             )
     *         )
     *     )
     * )
     */

    public function show(string $id)
    {
        try {
            $cliente = $this->clienteService->detalle($id);

            if ($cliente === null) {
                return response()->json(
                    ['error' => 'Cliente no encontrado'],
                    HttpStatusCode::NOT_FOUND->value
                );
            }

            return $this->apiResponse->successResponse(
                $cliente,
                'Cliente obtenido exitosamente',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'obtener clientes'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/clientes/{id}",
     *     summary="Actualizar un cliente",
     *     description="Actualiza la información de un cliente existente por su ID.",
     *     operationId="updateCliente",
     *     tags={"Clientes"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del cliente a actualizar",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos para actualizar el cliente",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"name","email","celular"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="Juan Pérez"
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 example="juan.perez@example.com"
     *             ),
     *             @OA\Property(
     *                 property="celular",
     *                 type="string",
     *                 example="987654321"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cliente actualizado con éxito",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Cliente actualizado con éxito."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Juan Pérez"),
     *                 @OA\Property(property="email", type="string", example="juan.perez@example.com"),
     *                 @OA\Property(property="celular", type="string", example="987654321")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cliente no encontrado",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Cliente no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Error al actualizar el cliente: mensaje del error")
     *         )
     *     )
     * )
     */

    public function update(UpdateClienteRequest $request, string $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(
                ['error' => 'Cliente no encontrado'],
                HttpStatusCode::NOT_FOUND->value
            );
        }

        try {
            $cliente = $this->clienteService->actualizar($cliente, $request->validated());

            return $this->apiResponse->successResponse(
                $cliente,
                'Cliente actualizado con éxito.',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'actualizar el cliente'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/clientes/{id}",
     *     summary="Eliminar un cliente",
     *     description="Elimina un cliente existente por su ID.",
     *     operationId="deleteCliente",
     *     tags={"Clientes"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del cliente a eliminar",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cliente eliminado exitosamente",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Cliente eliminado exitosamente"),
     *             @OA\Property(property="data", type="null", example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cliente no encontrado",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Cliente no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Error al eliminar el cliente: mensaje del error")
     *         )
     *     )
     * )
     */

    public function destroy(string $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(
                ['error' => 'Cliente no encontrado'],
                HttpStatusCode::NOT_FOUND->value
            );
        }

        try {
            $this->clienteService->eliminar($cliente);

            return $this->apiResponse->successResponse(
                null,
                'Cliente eliminado exitosamente',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'eliminar el cliente'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }

    public function paginate(Request $request)
    {
        $validate = $request->validate([
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $clientes = $this->clienteService->paginados(
            $validate['perPage'] ?? 10,
            $validate['page'] ?? 1
        );

        return response()->json([
            'data' => $clientes->items(),
        ]);
    }

    /**
     * Obtener lista avanzada de clientes con estadísticas de Whatsapp enbebidas
     */
    /**
     * Obtener lista avanzada de clientes con estadísticas de Whatsapp embebidas
     */
    public function getAdvancedList(Request $request)
    {
        try {
            $validated = $request->validate([
                'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'page' => ['sometimes', 'integer', 'min:1'],
            ]);

            $datos = $this->clienteService->listarConEstadisticas(
                $validated['perPage'] ?? 25,
                $validated['page'] ?? 1
            );

            return $this->apiResponse->successResponse(
                $datos,
                'Clientes obtenidos exitosamente',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {
            \Log::error('Error al obtener los clientes', [
                'exception' => $e,
            ]);

            return response()->json(
                ['error' => 'No se pudo obtener la lista de clientes.'],
                HttpStatusCode::INTERNAL_SERVER_ERROR->value
            );
        }
    }

}