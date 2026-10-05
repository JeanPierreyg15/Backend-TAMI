<?php

namespace App\Http\Controllers\Api\V1\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\HorarioAtencion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HorarioAtencionController extends Controller
{
    private const MAPA_DIAS = [
        'Lunes - Viernes' => 'LUN_VIE',
        'Sábado' => 'SABADO',
        'Domingo' => 'DOMINGO',
    ];

    public function index()
    {
        $horarios = HorarioAtencion::where('contacto_id', 1)->get();

        $dias = collect(self::MAPA_DIAS)->map(function ($grupo, $label) use ($horarios) {
            $fila = $horarios->firstWhere('dia_grupo', $grupo);

            return [
                'dia' => $label,
                'abierto' => $fila?->abierto ?? false,
                'hora_inicio' => $fila?->hora_inicio ?? '',
                'hora_fin' => $fila?->hora_fin ?? '',
            ];
        })->values();

        return response()->json(['dias' => $dias]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'dias' => 'required|array|size:3',
            'dias.*.dia' => 'required|string|in:Lunes - Viernes,Sábado,Domingo',
            'dias.*.abierto' => 'required|boolean',
            'dias.*.hora_inicio' => 'nullable|date_format:H:i',
            'dias.*.hora_fin' => 'nullable|date_format:H:i|after:dias.*.hora_inicio',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['dias'] as $dia) {
                $grupo = self::MAPA_DIAS[$dia['dia']];

                HorarioAtencion::updateOrCreate(
                    [
                        'contacto_id' => 1,
                        'dia_grupo' => $grupo,
                    ],
                    [
                        'abierto' => $dia['abierto'],
                        'hora_inicio' => $dia['abierto'] ? $dia['hora_inicio'] : null,
                        'hora_fin' => $dia['abierto'] ? $dia['hora_fin'] : null,
                    ]
                );
            }
        });

        return response()->json(['ok' => true]);
    }
}