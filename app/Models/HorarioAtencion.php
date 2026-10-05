<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioAtencion extends Model
{
    use HasFactory;

    protected $table = 'horario_atencion';
    protected $primaryKey = 'id';
    protected $fillable = [
        'contacto_id',
        'dia_grupo',
        'abierto',
        'hora_inicio',
        'hora_fin',
    ];
    protected $casts = [
        'abierto' => 'boolean',
    ];
}
