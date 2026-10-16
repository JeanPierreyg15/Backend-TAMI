<?php

namespace Database\Seeders;

use App\Models\ClienteSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClienteSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            1 => 'Inicio',
            2 => 'Producto detalle',
            3 => 'Administración',
            4 => 'Popup de Inicio',
        ];

        foreach ($sources as $id => $name) {
            DB::table('cliente_sources')->updateOrInsert(
                ['id' => $id],
                ['name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
