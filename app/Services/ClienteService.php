<?php

namespace App\Services;

use App\Models\CampaignMessageLog;
use App\Models\Cliente;
use App\Models\ClienteSource;
use App\Models\WhatsappMessageLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClienteService
{
    public function __construct(
        private ClienteEmailService $emailService,
    ) {}

    /**
     * Registra un cliente.
     *
     * Si la fuente es "Producto detalle" y ya existe un cliente con ese
     * email o celular, actualiza el existente en lugar de duplicarlo.
     *
     * @return array{cliente: Cliente, actualizado: bool}
     */
    public function registrar(array $datos): array
    {
        $resultado = DB::transaction(function () use ($datos) {
            $source = ClienteSource::find($datos['source_id']);

            if ($source && $source->name === 'Producto detalle') {
                $existente = Cliente::where('email', $datos['email'])->first()
                    ?? Cliente::where('celular', $datos['celular'])->first();

                if ($existente) {
                    $existente->update([
                        'name' => $datos['name'],
                        'email' => $datos['email'],
                        'celular' => $datos['celular'],
                        'source_id' => $datos['source_id'],
                        'producto_id' => $datos['producto_id'] ?? $existente->producto_id,
                    ]);

                    return ['cliente' => $existente->fresh(), 'actualizado' => true];
                }
            }

            $cliente = Cliente::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'celular' => $datos['celular'],
                'source_id' => $datos['source_id'],
                'producto_id' => $datos['producto_id'] ?? null,
            ]);

            return ['cliente' => $cliente->fresh(), 'actualizado' => false];
        });

        // Igual que antes: el flujo de upsert no envía correo.
        if (!$resultado['actualizado']) {
            $this->emailService->enviarBienvenida($resultado['cliente'], $datos);
        }

        return $resultado;
    }

    /**
     * Actualiza los campos editables del cliente.
     */
    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        $camposActualizar = array_intersect_key($datos, array_flip(['name', 'email', 'celular']));

        if ($camposActualizar !== []) {
            $cliente->update($camposActualizar);
        }

        return $cliente->fresh();
    }

    public function eliminar(Cliente $cliente): void
    {
        DB::transaction(fn () => $cliente->delete());
    }

    /**
     * Datos de un cliente para el endpoint de detalle.
     */
    public function detalle(int|string $id): ?array
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return null;
        }

        return [
            'id' => $cliente->id,
            'name' => $cliente->name,
            'email' => $cliente->email,
            'celular' => $cliente->celular,
        ];
    }

    public function paginados(int $perPage, int $page): LengthAwarePaginator
    {
        return Cliente::paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Listado con estadísticas de WhatsApp + totales globales.
     */
    public function listarConEstadisticas(int $perPage, int $page): array
    {
        $clientes = Cliente::with(['producto', 'source'])
            ->withCount(['whatsappMessages as whatsapp_total_messages'])
            ->withMax('whatsappMessages as whatsapp_ult_envio', 'created_at')
            ->withCount(['campaignMessages as campaign_total_messages' => function ($query) {
                $query->where('status', 'sent');
            }])
            ->withMax(['campaignMessages as campaign_ult_envio' => function ($query) {
                $query->where('status', 'sent');
            }], 'created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $clientes->through(function ($cliente) {
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
                        ],
                    ],
                ],
                'created_at' => $cliente->created_at,
            ];
        });

        return [
            'data' => $clientes,
            'pagination' => [
                'total' => $clientes->total(),
                'per_page' => $clientes->perPage(),
                'current_page' => $clientes->currentPage(),
                'last_page' => $clientes->lastPage(),
            ],
            'totals' => $this->estadisticasGlobales(),
        ];
    }

    private function estadisticasGlobales(): array
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
            ],
        ];
    }
}
