<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Http\Contains\HttpStatusCode;

trait SafeErrorTrait
{
    /**
     * Devuelve una respuesta de error segura dependiendo del modo debug.
     * 
     * @param \Throwable $e La excepción capturada
     * @param string $context Descripción de la acción que falló (ej. "iniciar sesión")
     * @param int $statusCode Código de estado HTTP (por defecto 500)
     * @return JsonResponse|string
     */
    protected function safeErrorMessage(\Throwable $e, string $context, int $statusCode = 500)
    {
        // Loguear siempre el error real para el administrador
        Log::error("Error al {$context}: " . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Si es un controlador que usa ApiResponseService, podrías retornar el mensaje
        // Pero para ser genérico aquí retornamos el string
        return config('app.debug')
            ? "Error al {$context}: " . $e->getMessage()
            : "Error al {$context}. Por favor, intente más tarde o contacte al administrador.";
    }
}
