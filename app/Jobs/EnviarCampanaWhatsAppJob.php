<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\CampaignMessageLog;

class EnviarCampanaWhatsAppJob implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels, \App\Traits\FormatsTextTrait;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public string $celular,
        public string $mensaje,
        public ?string $imagenPath,
        public string $nombre,
        public int $campanaId,
        public int $clienteId
    ) {}

    public function handle(): void
    {
        // Crear registro inicial con status pending
        $log = CampaignMessageLog::create([
            'campana_id' => $this->campanaId,
            'cliente_id' => $this->clienteId,
            'phone' => $this->celular,
            'status' => 'pending',
        ]);

        try {
            $mensajeBase = "Hola {$this->nombre}, {$this->mensaje}";
            $mensajeFinal = $this->formatHtmlForWhatsapp($mensajeBase);

            $imageUrl = $this->imagenPath
                ? asset('storage/' . $this->imagenPath)
                : null;

            $url = config('services.whatsapp.base_url') . '/whatsapp/send-campaign';

            $response = Http::post($url, [
                'phone'   => $this->celular,
                'message' => $mensajeFinal,
                'image'   => $imageUrl,
            ]);

            Log::info('Respuesta de WhatsApp Node', [
                'status'   => $response->status(),
                'body'     => $response->body(),
                'telefono' => $this->celular,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Error Node: ' . $response->body());
            }

            // Actualizar log como enviado
            $log->update(['status' => 'sent']);

        } catch (\Throwable $e) {
            // Actualizar log como fallido
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Error enviando campaña', [
                'telefono' => $this->celular,
                'error'    => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
