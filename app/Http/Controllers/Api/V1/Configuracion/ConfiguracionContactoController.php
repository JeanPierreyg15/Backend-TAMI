<?php

namespace App\Http\Controllers\Api\V1\Configuracion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ConfiguracionContacto;

class ConfiguracionContactoController extends Controller
{
    public function index()
    {
        $contacto = ConfiguracionContacto::find(1);
        return response()->json($contacto);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'correo' => 'required|email|max:100',
            'telefono' => 'required|digits:9|starts_with:9',
            'telefonoOpcional' => 'nullable|digits:9|starts_with:9',
            'direccion' => 'required|string|min:5|max:200',
        ]);

        $contacto = ConfiguracionContacto::updateOrCreate(
            ['id' => 1], 
            [
                'correo' => $data['correo'],
                'telefono' => $data['telefono'],
                'telefono_opcional' => $data['telefonoOpcional'] ?? null,
                'direccion' => $data['direccion'],
            ]
        );

        return response()->json($contacto);
    }
}