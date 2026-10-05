<?php

use App\Http\Controllers\Api\V2\V2ClienteController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Blog\BlogController;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\Producto\ProductoController;
use App\Http\Controllers\Api\V1\Cliente\ClienteController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Api\V1\Email\EmailController;
use App\Http\Controllers\Api\V1\WhatsApp\WhatsAppController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Api\V1\WhatsApp\CampañaController;

use App\Http\Controllers\Api\V1\Reclamos\ClaimController;
use App\Http\Controllers\Api\V1\Reclamos\ContactMessageController;

use App\Http\Controllers\Api\V1\Deploy\FrontendDeployController;
use App\Http\Controllers\Api\V1\HomePopup\HomePopupSettingController;
use App\Http\Controllers\Api\V1\Chatbot\ChatbotController;

use App\Http\Controllers\Api\V1\Configuracion\ConfiguracionContactoController;
use App\Http\Controllers\Api\V1\Configuracion\RedSocialController;
use App\Http\Controllers\Api\V1\Configuracion\HorarioAtencionController;



Route::prefix('v1')->group(function () {

    Route::controller(HomePopupSettingController::class)->prefix('popup-settings')->group(function () {
        Route::middleware('throttle:api')->get('/public', 'showPublic');
    });

    Route::controller(AuthController::class)->prefix('auth')->group(function () {
        Route::middleware('throttle:login')->post('/login', 'login');
        Route::post('/logout', 'logout')->middleware(['auth:sanctum', 'role:ADMIN|USER']);
        Route::post('/refresh', 'refresh');
    });

    Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
        Route::apiResource('users', UserController::class);

        Route::controller(HomePopupSettingController::class)->prefix('admin/popup-settings')->group(function () {
            Route::get('/', 'showAdmin');
            Route::match(['POST', 'PUT', 'PATCH'], '/', 'update');
        });
    });

    // Crear cliente (Público)
    Route::post('clientes', [ClienteController::class, 'store']);
    // Clientes (Solo ADMIN y VENTAS)
    Route::middleware(['auth:sanctum', 'role:ADMIN|VENTAS'])->group(function () {
        Route::controller(ClienteController::class)->prefix('clientes')->group(function () {
            Route::get('/paginate', 'paginate');
            Route::get('/', 'getAdvancedList');
            Route::get('/{cliente}', 'show');
            Route::put('/{cliente}', 'update');
            Route::delete('/{cliente}', 'destroy');
        });
    });

    Route::controller(BlogController::class)->prefix('blogs')->group(function () {
        Route::middleware('throttle:api')->get('/', 'index');
        Route::middleware('throttle:api')->get('/paginate', 'paginate');
        Route::middleware('throttle:api')->get('/link/{link}', 'showLink');
        Route::middleware('throttle:api')->get('/{id}', 'show');

        #protegidas
        Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
            Route::post('/', 'store');
            Route::match(['POST', 'PUT'], '/{blog}', 'update');
            Route::delete('/{blog}', 'destroy');
        });
    });

    Route::prefix('configuracion')->middleware('throttle:api')->group(function () {
        Route::get('/contacto', [ConfiguracionContactoController::class, 'index']);
        Route::get('/redes', [RedSocialController::class, 'index']);
        Route::get('/horario', [HorarioAtencionController::class, 'index']);
    });
 
    // Escritura solo ADMIN
    Route::middleware(['auth:sanctum', 'role:ADMIN'])->prefix('configuracion')->group(function () {
        Route::put('/contacto', [ConfiguracionContactoController::class, 'update']);
        Route::put('/redes', [RedSocialController::class, 'update']);
        Route::put('/horario', [HorarioAtencionController::class, 'update']);
    });

    // Rutas de Email
    Route::controller(EmailController::class)->prefix('/emails')->group(function () {
        // Enviar correo general (Protegido por seguridad)
        Route::middleware(['throttle:public-forms'])->post('/', 'sendEmail');
        // Enviar correo de producto (Público para el popup de clientes)
        Route::middleware(['throttle:public-forms'])->post('/product-link', 'sendEmailByProductLink');
    });

    Route::controller(ProductoController::class)->prefix('productos')->group(function () {
        // Rutas públicas con Rate Limit (60/min)
        Route::middleware('throttle:api')->group(function () {
            Route::get('/', 'index');
            Route::get('/paginate', 'paginate');
            Route::get('/{id}', 'show');
            Route::get('/{id}/related', 'related');
            Route::get('/link/{link}', 'showByLink');
        });

        // Rutas protegidas (Solo ADMIN)
        Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
            Route::post('/', 'store');
            Route::match(['POST', 'PUT', 'PATCH'], '/{id}', 'update');
            Route::delete('/{id}', 'destroy');
        });
    });

    Route::controller(ChatbotController::class)->prefix('chatbot')->group(function () {
        Route::middleware('throttle:api')->group(function () {
            Route::get('/icon', 'getIcon');
            Route::get('/head-color', 'getHeaderColor');
            Route::get('/salute', 'getSaludo');
            Route::get('/posicion', [ChatbotController::class, 'getPosicion']);
        });

        // Rutas protegidas (Solo ADMIN)
        Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
            Route::post('/icon', 'updateIcon');
            Route::post('/head-color', 'updateHeaderColor');
            Route::post('/salute', 'updateSaludo');
            Route::post('/posicion', [ChatbotController::class, 'updatePosicion']);
        });
    });

    Route::controller(WhatsAppController::class)->prefix('whatsapp')->group(function () {
        Route::middleware('throttle:public-forms')->post('/solicitar-info-producto', 'sendProductDetails');
        Route::middleware('throttle:public-forms')->post('/popup-submission', 'sendPopUpDetails');

        // Rutas protegidas de WhatsApp Admin
        Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
            Route::post('/request-qr', 'requestQR');
            Route::post('/reset', 'resetSession');
            Route::get('/template/product/{productoId}', 'showByProduct');
            Route::match(['POST', 'PUT'], '/template/product/{productoId}', 'updateTemplateByProduct');
            Route::delete('/template/product/{productoId}', 'deleteTemplateByProduct');
        });
    });



    // ------------------- RECLAMOS (Público) -------------------
    Route::middleware('throttle:public-forms')->post('claims', [ClaimController::class, 'store']);

    // Datos para formularios públicos
    Route::middleware('throttle:api')->get('claim-form-data', [ClaimController::class, 'formData']);

    // ------------------- CONTACTO (Público) -------------------
    Route::middleware('throttle:public-forms')->post('contacto', [ContactMessageController::class, 'store']);

    // ------------------- ADMINISTRACIÓN RECLAMOS Y CONTACTO (Solo ADMIN) -------------------
    Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
        // Gestión de Reclamos
        Route::controller(ClaimController::class)->prefix('admin/claims')->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
            Route::patch('/{id}/status', 'updateStatus');
        });

        // Gestión de Mensajes de Contacto
        Route::controller(ContactMessageController::class)->prefix('admin/contacto')->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
            Route::delete('/{id}', 'destroy');
        });
    });

    // Deploy Frontend (solo ADMIN)
    Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
        Route::post(
            'frontend/deploy',
            [FrontendDeployController::class, 'deploy']
        );
    });




    // Las rutas individuales anteriores se movieron dentro del grupo protegido de WhatsAppController




    // Rutas para campañas de WhatsApp
    // ------------------- CAMPAÑAS WHATSAPP (Solo ADMIN) -------------------
    Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
        Route::controller(CampañaController::class)
            ->prefix('whatsapp/campañas')
            ->group(function () {
                // activar campaña
                Route::post('/activar', 'activar');

                // opcional: listar campañas
                Route::get('/', 'index');

                // opcional: ver campaña
                Route::get('/{id}', 'show');
            });
    });


    // ------------------- CHATBOT -------------------
    // Limitamos a 20 mensajes por minuto por usuario (IP) para evitar ataques de SPAM.
    Route::middleware('throttle:20,1')->post('chat/responder', [\App\Http\Controllers\Api\V1\Chatbot\ChatbotController::class, 'ask']);

});


// Route::prefix("v2")->group(function(){
//     Route::controller(V2ClienteController::class)->prefix("/clientes")->group(function(){
//         Route::middleware(["auth:sanctum", "role:ADMIN"])->group(function(){
//             Route::get("/", "index");
//             Route::get("/{id}", 'show');
//             Route::post("/", "store");
//             Route::put("/{id}", "update");
//             Route::delete("/{id}", "destroy");
//         });
//     });

// Route::controller(V2ProductoController::class)->prefix('productos')->group(function(){
//     Route::get('/', 'paginate');
//     Route::get('/all', 'index');
//     Route::get('/{id}', 'show');

//     Route::middleware(['auth:sanctum', 'role:ADMIN|USER', 'permission:ENVIAR'])->group(function () {});

//     Route::post('/', 'store');
//     Route::put('/{id}', 'update');
//     Route::delete('/{id}', 'destroy');
//     Route::get('/link/{link}', 'showByLink');
// });
// });


Route::controller(PermissionController::class)->prefix("permisos")->group(function () {
    Route::middleware(["auth:sanctum", 'role:ADMIN'])->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');

        //Asignaciones
        Route::post('/assign-permission', 'assignPermissionToRole');
        Route::post('/remove-permission', 'removePermissionFromRole');
        Route::get('/getRolePermissions/{id}', 'getRolePermissions');
    });
});

Route::controller(RoleController::class)->prefix("roles")->group(function () {
    Route::middleware(["auth:sanctum", 'role:ADMIN'])->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');

        //Asignaciones
        Route::post('/assign-role/{id}', 'assignRoleToUser');
        Route::post('/remove-role/{id}', 'removeRoleFromUser');
        Route::get('/getUserRoles/{id}', 'getUserRoles');
    });
});
