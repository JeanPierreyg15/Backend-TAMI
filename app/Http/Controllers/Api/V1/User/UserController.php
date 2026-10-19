<?php


namespace App\Http\Controllers\Api\V1\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostUser\PostUser;
use App\Http\Requests\PostUser\PostUserUpdate;
use App\Http\Contains\HttpStatusCode;
use App\Models\User;
use App\Services\ApiResponseService;
use Illuminate\Support\Facades\DB;
use App\Traits\SafeErrorTrait;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Usuarios",
 *     description="API para gestión de usuarios"
 * )
 */
class UserController extends Controller
{
    use SafeErrorTrait;
    protected ApiResponseService $apiResponse;

    public function __construct(ApiResponseService $apiResponse) {
        $this->apiResponse = $apiResponse;
    }

    
    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     summary="Listar todos los usuarios",
     *     description="Obtiene una lista de todos los usuarios registrados con sus roles",
     *     operationId="indexUsers",
     *     tags={"Usuarios"},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de usuarios obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuarios listados correctamente."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *                     @OA\Property(property="celular", type="string", example="1234567890"),
     *                     @OA\Property(property="fecha", type="string", format="date", example="1990-01-01"),
     *                     @OA\Property(property="role", type="string", example="USER")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Ocurrió un problema al listar los usuarios"),
     *             @OA\Property(property="errors", type="null")
     *         )
     *     )
     * )
     */
    public function index() {
        try {
            $userList = User::paginate(10);

            $message = $userList->isEmpty() ? "No hay usuarios disponibles." : "Usuarios listados correctamente.";
            return $this->apiResponse->successResponse($userList, $message);
        } catch (\Exception $e) {
            return $this->apiResponse->internalServerErrorResponse("Ocurrió un problema al listar los usuarios: " . $e->getMessage());
        }
    }
    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     summary="Crear un nuevo usuario",
     *     description="Crea un nuevo usuario y le asigna el rol USER",
     *     operationId="storeUser",
     *     tags={"Usuarios"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","celular","fecha"},
     *             @OA\Property(property="name", type="string", example="John Doe", description="Nombre del usuario"),
     *             @OA\Property(property="email", type="string", format="email", example="johndoe@example.com", description="Correo electrónico"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="Contraseña"),
     *             @OA\Property(property="celular", type="string", example="1234567890", description="Número de celular"),
     *             @OA\Property(property="fecha", type="string", format="date", example="1990-01-01", description="Fecha de nacimiento"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario creado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario creado correctamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Los datos proporcionados no son válidos"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al crear usuario"),
     *             @OA\Property(property="errors", type="null")
     *         )
     *     )
     * )
     */
    public function store(PostUser $request) {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            //Contraseña por defecto, hasta que el fronted la envie.
            $data["password"] = Hash::make('password');

            $user = User::create($data);
            $user->assignRole($data['role'] ?? 'USER');

            DB::commit();

            return $this->apiResponse->successResponse($user, 'Usuario creado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse->internalServerErrorResponse('Error al crear usuario: ' . $e->getMessage());
        }
    }

    
    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     tags={"Usuarios"},
     *     summary="Obtener un usuario específico",
     *     description="Retorna los datos de un usuario según su ID",
     *     operationId="showUsuario",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del usuario a consultar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario encontrado o no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario encontrado."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="celular", type="string", example="999888777"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado."),
     *             @OA\Property(property="data", type="null", example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Ocurrió un problema al procesar la solicitud. Detalles del error.")
     *         )
     *     )
     * )
     */
        public function show($id) {
        try {
            $user = User::find($id);

            return $this->apiResponse->successResponse(
                $user,
                $user ? 'Usuario encontrado.' : 'Usuario no encontrado.',
                $user ? HttpStatusCode::OK : HttpStatusCode::NOT_FOUND
            );
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(
                $this->safeErrorMessage($e, 'obtener los usuarios'),
                HttpStatusCode::INTERNAL_SERVER_ERROR
            );
        }
    }

    
    /**
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     summary="Actualizar usuario",
     *     description="Actualiza los datos de un usuario existente",
     *     operationId="updateUser",
     *     tags={"Usuarios"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del usuario a actualizar",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe Updated", description="Nombre del usuario"),
     *             @OA\Property(property="email", type="string", format="email", example="johndoeupdated@example.com", description="Correo electrónico"),
     *             @OA\Property(property="celular", type="string", example="0987654321", description="Número de celular"),
     *             @OA\Property(property="fecha", type="string", format="date", example="1990-01-01", description="Fecha de nacimiento"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario actualizado correctamente."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Recurso no encontrado"),
     *             @OA\Property(property="errors", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Los datos proporcionados no son válidos"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Ocurrió un problema al actualizar al usuario"),
     *             @OA\Property(property="errors", type="null")
     *         )
     *     )
     * )
     */
    public function update(PostUserUpdate $request, $id) {
        try {
            $user = User::findOrFail($id);
            $user->update($request->validated());

            $message = $user->wasChanged()
                ? "Usuario actualizado correctamente."
                : "No hubo cambios en los datos del usuario.";

            return $this->apiResponse->successResponse($user, $message);
        } catch (ModelNotFoundException $e) {
            return $this->apiResponse->notFoundResponse('Usuario no encontrado.');
        } catch (\Exception $e) {
            return $this->apiResponse->internalServerErrorResponse(
                $this->safeErrorMessage($e, 'actualizar al usuario')
            );
        }
    }

    
    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     summary="Eliminar usuario",
     *     description="Elimina un usuario por su ID",
     *     operationId="destroyUser",
     *     tags={"Usuarios"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del usuario a eliminar",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario eliminado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario eliminado correctamente."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Recurso no encontrado"),
     *             @OA\Property(property="errors", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Ocurrió un problema con la eliminación del usuario"),
     *             @OA\Property(property="errors", type="null")
     *         )
     *     )
     * )
     */
    public function destroy($id) {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return $this->apiResponse->successResponse(
                $user,
                'Usuario eliminado correctamente.',
                HttpStatusCode::OK
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->apiResponse->notFoundResponse("Usuario no encontrado.");
        } catch (\Exception $e) {
            return $this->apiResponse->internalServerErrorResponse(
                "Ocurrió un problema con la eliminación del usuario: " . $e->getMessage()
            );
        }
    }
}

