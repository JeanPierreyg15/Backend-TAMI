<?php

namespace App\Http\Controllers\Api\V1\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Cliente;
use App\Services\ApiResponseService;
use Illuminate\Http\Response;
use App\Http\Contains\HttpStatusCode;
use App\Mail\ClientRegistrationMail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Traits\SafeErrorTrait;
use App\Models\HomePopupSetting;
use App\Models\ClienteSource;
use App\Models\WhatsappMessageLog;
use App\Models\CampaignMessageLog;

class ClienteController extends Controller
{
    use SafeErrorTrait;
    protected ApiResponseService $apiResponse;

    public function __construct(ApiResponseService $apiResponse)
    {
        $this->apiResponse = $apiResponse;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clientes",
     *     summary="Listar clientes",
     *     description="Obtiene la lista de todos los clientes registrados con sus datos básicos.",
     *     operationId="getClientes",
     *     tags={"Clientes"},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de clientes obtenida exitosamente",
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
     *                 example="Clientes obtenidos exitosamente"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="name",
     *                         type="string",
     *                         example="Juan Pérez"
     *                     ),
     *                     @OA\Property(
     *                         property="email",
     *                         type="string",
     *                         format="email",
     *                         example="juan.perez@example.com"
     *                     ),
     *                     @OA\Property(
     *                         property="celular",
     *                         type="string",
     *                         example="+51 987654321"
     *                     )
     *                 )
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
     *                 example="Error al obtener los clientes: mensaje del error"
     *             )
     *         )
     *     )
     * )
     */

    public function index()
    {
        try{
            $cliente = Cliente::with(['producto', 'source'])->get();

            $showClient = $cliente->map(function ($cliente){
                return [
                    'id' => $cliente->id,
                    'name' => $cliente->name,
                    'email' => $cliente->email,
                    'celular' => $cliente->celular,
                    'producto' => $cliente->producto ? $cliente->producto->nombre : null,
                    'source' => $cliente->source ? $cliente->source->name : null,
                    'created_at' => $cliente->created_at,
                ];
            });

            return $this->apiResponse->successResponse(
                $showClient,
                'Clientes obtenidos exitosamente',
                HttpStatusCode::OK
            );
        }
        catch(\Exception $e){
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'obtener los clientes'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }

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
        $datosValidados = $request->validated();
        DB::beginTransaction();

        try
        {
            $shouldUpsert = false;

            if (isset($datosValidados['source_id'])) {
                $source = ClienteSource::find($datosValidados['source_id']);
                $shouldUpsert = $source && $source->name === 'Producto detalle';
            }

            if ($shouldUpsert) {
                // Buscar cliente existente por email o celular
                $clienteExistente = Cliente::where('email', $datosValidados['email'])->first()
                 ?? Cliente::where('celular', $datosValidados['celular'])->first();

                if ($clienteExistente) {
                    // Actualizar cliente existente
                    $clienteExistente->update([
                        'name' => $datosValidados['name'],
                        'email' => $datosValidados['email'],
                        'celular' => $datosValidados['celular'],
                        'source_id' => $datosValidados['source_id'],
                        'producto_id' => $datosValidados['producto_id'] ?? $clienteExistente->producto_id,
                    ]);

                    $clienteExistente->refresh();

                    DB::commit();
                    return $this->apiResponse->successResponse(
                        $clienteExistente->fresh(),
                        'Cliente actualizado con éxito.',
                        HttpStatusCode::OK
                    );
                }
            }

            $cliente = Cliente::create(
            [
                'name' => $datosValidados['name'],
                'email' => $datosValidados['email'],
                'celular' => $datosValidados['celular'],
                'source_id' => $datosValidados['source_id'],
                'producto_id' => $datosValidados['producto_id'] ?? null,
            ]
            );

            DB::commit();

            // Enviar correo de bienvenida si se proporcionó email
            if ($request->email) {
                try {
                    $enviadoProducto = false;

                    if (!empty($datosValidados['producto_id'])) {
                        $producto = \App\Models\Producto::with(['emailPasos', 'imagenes'])->find($datosValidados['producto_id']);
                        if ($producto) {
                            $emailPaso1 = $producto->emailPasos->firstWhere('paso', 1);
                            if ($emailPaso1) {
                                $asunto = $emailPaso1->asunto ?? 'Información sobre ' . $producto->nombre;
                                $mensaje = $emailPaso1->mensaje ?? '';
                                $mensaje = str_replace('{{nombre}}', $request->name, $mensaje);

                                $productData = [
                                    'name' => $producto->nombre,
                                    'main_image' => !empty($emailPaso1->imagen_url)
                                        ? url($emailPaso1->imagen_url)
                                        : asset('email/default-product.webp'),
                                    'video_url' => $producto->video_url ?? null,
                                    'client_name' => $request->name,
                                    'mensaje_html' => $mensaje,
                                ];

                                Mail::to($request->email)->send(
                                    new \App\Mail\ProductInfoMail(['product' => $productData], 'emails.product-generic', $asunto)
                                );
                                $enviadoProducto = true;
                            }
                        }
                    }

                    if (!$enviadoProducto && !empty($datosValidados['producto_id'])) {
                        $producto = \App\Models\Producto::find($datosValidados['producto_id']);
                        if ($producto) {
                            // Fallback para productos: enviar información básica en lugar del Home Popup
                            $imagenPrincipal = $producto->imagenes()->where('tipo', 'galeria')->first()
                                            ?? $producto->imagenes()->first();

                            $productData = [
                                'name' => $producto->nombre,
                                'main_image' => $imagenPrincipal
                                    ? url($imagenPrincipal->url_imagen)
                                    : asset('email/default-product.webp'),
                                'video_url' => $producto->video_url ?? null,
                                'client_name' => $request->name,
                                'mensaje_html' => "Información sobre {$producto->nombre}<br><br>Precio: S/ {$producto->precio}<br><br>Puedes ver más detalles aquí: <a href='" . url('/productos/' . $producto->link) . "'>Ver producto</a>",
                            ];

                            Mail::to($request->email)->send(
                                new \App\Mail\ProductInfoMail(['product' => $productData], 'emails.product-generic', 'Información sobre ' . $producto->nombre)
                            );
                            $enviadoProducto = true;
                        }
                    }

                    if (!$enviadoProducto) {
                        $setting = HomePopupSetting::with(['emailSteps'])->first();
                    }

                    if (!$enviadoProducto && $setting && $setting->email_enabled) {
                        $emailSteps = $setting->emailSteps->keyBy('sequence');
                        $defaultDelays = [0, 30, 1440];

                        foreach ($emailSteps as $step) {
                            $delay = $step->delay_minutes !== null ? (int) $step->delay_minutes : $defaultDelays[$step->sequence - 1];

                            if ($delay !== -1) {
                                \App\Jobs\SendMarketingEmailJob::dispatch($cliente, $step->sequence, [
                                    'subject' => $step->subject,
                                    'message' => $step->message,
                                    'image_url' => $step->image_url,
                                    'btn_text' => $step->btn_text,
                                    'btn_link' => $step->btn_link,
                                    'btn_bg_color' => $step->btn_bg_color,
                                    'btn_text_color' => $step->btn_text_color,
                                ])->delay(now()->addMinutes($delay));
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error al enviar correo de registro: ' . $e->getMessage());
                    // No cortamos el flujo si falla el correo
                }
            }

            return $this->apiResponse->successResponse($cliente->fresh(), 'Cliente creado con éxito', HttpStatusCode::CREATED);
        } catch (\Exception $e) {
            DB::rollBack();

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
            $cliente = Cliente::find($id);

            if (!$cliente) {
                return response()->json(['error' => 'Cliente no encontrado'], HttpStatusCode::NOT_FOUND->value);
            }

            $showClient = [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'celular' => $cliente->celular,
            ];

            return $this->apiResponse->successResponse(
                $showClient,
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
        $datosValidados = $request->validated();

        $cliente = Cliente::find($id);
        if (!$cliente){
            return response() -> json (
                ['error' => 'Cliente no encontrado'],
                HttpStatusCode::NOT_FOUND->value
            );
        }

        DB::beginTransaction();

        try
        {
            $camposActualizar = [];

            foreach (['name', 'email', 'celular'] as $campo) {
                if (array_key_exists($campo, $datosValidados)) {
                    $camposActualizar[$campo] = $datosValidados[$campo];
                }
            }

            if (!empty($camposActualizar)) {
                $cliente->update($camposActualizar);
            }

            /*$cliente->update([
                'name' => $datosValidados['name'],
                'email' => $datosValidados['email'],
                'celular' => $datosValidados['celular']
            ]);*/

            DB::commit();
            return $this->apiResponse->successResponse($cliente->fresh(), 'Cliente actualizado con éxito.', HttpStatusCode::OK);
        }
        catch (\Exception $e) {
            DB::rollBack();
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
        if (!$cliente){
            return response() -> json (
                ['error' => 'Cliente no encontrado'],
                HttpStatusCode::NOT_FOUND->value
            );
        }

        DB::beginTransaction();

        try{
            $cliente->delete();

            DB::commit();

            return $this->apiResponse->successResponse(
                null,
                'Cliente eliminado exitosamente',
                HttpStatusCode::OK
            );
        }
        catch (\Exception $e) {
            DB::rollBack();

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

        $perPage = $validate['perPage'] ?? 10;
        $page = $validate['page'] ?? 1;

        $clientes = Cliente::paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data'=> $clientes->items()
        ]);
    }

    /**
     * Obtener lista avanzada de clientes con estadísticas de Whatsapp enbebidas
     */
    public function getAdvancedList(Request $request)
    {
        try {
            $validated = $request->validate([
                'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'page' => ['sometimes', 'integer', 'min:1'],
            ]);

            $perPage = $validated['perPage'] ?? 25;
            $page = $validated['page'] ?? 1;

            // Obtener clientes con relaciones y estadísticas de WhatsApp
            $clientes = Cliente::with(['producto', 'source'])
                ->withCount(['whatsappMessages as whatsapp_total_messages'])
                ->withMax('whatsappMessages as whatsapp_ult_envio', 'created_at')
                ->withCount(['campaignMessages as campaign_total_messages' => function($query) {
                    $query->where('status', 'sent');
                }])
                ->withMax(['campaignMessages as campaign_ult_envio' => function($query) {
                    $query->where('status', 'sent');
                }], 'created_at')
                ->paginate($perPage, ['*'], 'page', $page);

            // Mapear clientes para incluir estadísticas
            $clientesWithStats = $clientes->through(function ($cliente) {
                return [
                    'id' => $cliente->id,
                    'name' => $cliente->name,
                    'email' => $cliente->email,
                    'celular' => $cliente->celular,
                    'producto' => $cliente->producto ? $cliente->producto->nombre : null,
                    'source' => $cliente->source ? $cliente->source->name : null,
                    'stats' => [
                        'whatsapp' => [
                            'popup' => [
                                'total_messages' => $cliente->whatsapp_total_messages,
                                'ult_envio' => $cliente->whatsapp_ult_envio,
                            ],
                            'campaign' => [
                                'total_messages' => $cliente->campaign_total_messages,
                                'ult_envio' => $cliente->campaign_ult_envio,
                            ]
                        ]
                    ],
                    'created_at' => $cliente->created_at,
                ];
            });

            // obtener lista global de estadísticas de WhatsApp
            $globalStats = $this->getGlobalStats();

            return $this->apiResponse->successResponse(
                [
                    'data' => $clientesWithStats,
                    'pagination' => [
                        'total' => $clientes->total(),
                        'per_page' => $clientes->perPage(),
                        'current_page' => $clientes->currentPage(),
                        'last_page' => $clientes->lastPage(),
                    ],
                    'totals' => $globalStats,
                ],
                'Clientes obtenidos exitosamente',
                HttpStatusCode::OK
            );
        } catch (\Exception $e) {

            \Log::error('Error al obtener los clientes', [
                'exception' => $e,
            ]);

            return response()->json(['error' => 'No se pudo obtener la lista de clientes.'], HttpStatusCode::INTERNAL_SERVER_ERROR->value);
        }
    }

    /**
     * Obtener estadísticas detalladas de un cliente específico
     */
    public function getClientStats($id)
    {
        $cliente = Cliente::with(['producto', 'source', 'whatsappMessages', 'campaignMessages'])->find($id);

        return [
            'id' => $cliente->id,
            'name' => $cliente->name,
            'email' => $cliente->email,
            'celular' => $cliente->celular,
            'producto' => $cliente->producto ? $cliente->producto->nombre : null,
            'source' => $cliente->source ? $cliente->source->name : null,
            'stats' => [
                'whatsapp' => [
                    'popup' => [
                        'total_messages' => $cliente->whatsappMessages->count(),
                        'ult_envio' => $cliente->whatsappMessages->max('created_at'),
                    ],
                    'campaign' => [
                        'total_messages' => $cliente->campaignMessages->count(),
                        'ult_envio' => $cliente->campaignMessages->max('created_at'),
                    ]
                ]
            ],
        ];
    }

    /**
     * Obtener estadísticas globales de mensajes WhatsApp
     */
    public function getGlobalStats()
    {
        return [
            'whatsapp' => [
                'popup' => [
                    'total_messages' => WhatsappMessageLog::count(),
                    'ult_envio' => WhatsappMessageLog::max('created_at'),
                ],
                'campaign' => [
                    'total_messages' => CampaignMessageLog::where('status', 'sent')->count(),
                    'ult_envio' => CampaignMessageLog::where('status', 'sent')->max('created_at'),
                ],
            ]
        ];
    }
}
