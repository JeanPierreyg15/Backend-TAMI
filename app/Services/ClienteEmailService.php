<?php

namespace App\Services;

use App\Jobs\SendMarketingEmailJob;
use App\Mail\ProductInfoMail;
use App\Models\Cliente;
use App\Models\HomePopupSetting;
use App\Models\Producto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClienteEmailService
{
    /**
     * Envía el correo de bienvenida tras registrar un cliente.
     *
     * Un fallo aquí NO debe tumbar el registro: el cliente ya está creado.
     */
    public function enviarBienvenida(Cliente $cliente, array $datos): void
    {
        if (empty($datos['email'])) {
            return;
        }

        try {
            $enviadoProducto = $this->enviarCorreoDeProducto($datos);

            if (!$enviadoProducto) {
                $this->programarPasosDelPopup($cliente);
            }
        } catch (\Exception $e) {
            Log::error('Error al enviar correo de registro: ' . $e->getMessage());
        }
    }

    /**
     * Correo con la información del producto solicitado.
     * Devuelve true si se envió algo.
     */
    private function enviarCorreoDeProducto(array $datos): bool
    {
        $productoId = $datos['producto_id'] ?? null;

        if (empty($productoId)) {
            return false;
        }

        $producto = Producto::with(['emailPasos', 'imagenes'])->find($productoId);

        if (!$producto) {
            return false;
        }

        $emailPaso1 = $producto->emailPasos->firstWhere('paso', 1);

        if ($emailPaso1) {
            $asunto = $emailPaso1->asunto ?? 'Información sobre ' . $producto->nombre;
            $mensaje = str_replace('{{nombre}}', $datos['name'], $emailPaso1->mensaje ?? '');

            Mail::to($datos['email'])->send(
                new ProductInfoMail(
                    ['product' => [
                        'name' => $producto->nombre,
                        'main_image' => !empty($emailPaso1->imagen_url)
                            ? url($emailPaso1->imagen_url)
                            : asset('email/default-product.webp'),
                        'video_url' => $producto->video_url ?? null,
                        'client_name' => $datos['name'],
                        'mensaje_html' => $mensaje,
                    ]],
                    'emails.product-generic',
                    $asunto
                )
            );

            return true;
        }

        // Fallback: información básica del producto
        $imagenPrincipal = $producto->imagenes()->where('tipo', 'galeria')->first()
            ?? $producto->imagenes()->first();

        Mail::to($datos['email'])->send(
            new ProductInfoMail(
                ['product' => [
                    'name' => $producto->nombre,
                    'main_image' => $imagenPrincipal
                        ? url($imagenPrincipal->url_imagen)
                        : asset('email/default-product.webp'),
                    'video_url' => $producto->video_url ?? null,
                    'client_name' => $datos['name'],
                    'mensaje_html' => "Información sobre {$producto->nombre}<br><br>Precio: S/ {$producto->precio}<br><br>Puedes ver más detalles aquí: <a href='" . url('/productos/' . $producto->link) . "'>Ver producto</a>",
                ]],
                'emails.product-generic',
                'Información sobre ' . $producto->nombre
            )
        );

        return true;
    }

    /**
     * Programa la secuencia de correos del home popup.
     */
    private function programarPasosDelPopup(Cliente $cliente): void
    {
        $setting = HomePopupSetting::with(['emailSteps'])->first();

        if (!$setting || !$setting->email_enabled) {
            return;
        }

        $defaultDelays = [0, 30, 1440];

        foreach ($setting->emailSteps->keyBy('sequence') as $step) {
            $delay = $step->delay_minutes !== null
                ? (int) $step->delay_minutes
                : $defaultDelays[$step->sequence - 1];

            if ($delay === -1) {
                continue;
            }

            SendMarketingEmailJob::dispatch($cliente, $step->sequence, [
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
