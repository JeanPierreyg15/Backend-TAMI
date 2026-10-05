<?php

namespace App\Http\Controllers\Api\V1\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionContacto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RedSocialController extends Controller
{
    public function index()
    {
        $contacto = ConfiguracionContacto::find(1);

        return response()->json(
            $contacto ? $contacto->redesSociales()->get() : []
        );
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'redes'          => 'present|array|max:14',
            'redes.*.id'     => 'nullable|integer',
            'redes.*.tipo'   => 'required|string|max:50',
            'redes.*.nombre' => 'required|string|max:100',
            'redes.*.url'    => 'required|url|max:200',
            'redes.*.orden'  => 'required|integer|min:1',
        ]);

        $contacto = ConfiguracionContacto::find(1);

        if (!$contacto) {
            return response()->json([
                'message' => 'Primero configura la información de contacto.',
            ], 422);
        }

        DB::transaction(function () use ($contacto, $data) {
            $ids = collect($data['redes'])->pluck('id')->filter()->all();
            $contacto->redesSociales()->whereNotIn('id', $ids)->delete();

            foreach ($data['redes'] as $red) {
                $attrs = [
                    'tipo'   => $red['tipo'],
                    'nombre' => $red['nombre'],
                    'url'    => $red['url'],
                    'orden'  => $red['orden'],
                ];

                if (!empty($red['id'])) {
                    $contacto->redesSociales()->findOrFail($red['id'])->update($attrs);
                } else {
                    $contacto->redesSociales()->create($attrs);
                }
            }
        });

        return response()->json($contacto->redesSociales()->get());
    }
}