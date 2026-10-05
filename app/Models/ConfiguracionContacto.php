<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionContacto extends Model
{
    use HasFactory;

    protected $table = 'configuracion_contacto';
    protected $primaryKey = 'id';
    protected $fillable = [
        'correo',
        'telefono',
        'telefono_opcional',
        'direccion',
    ];

    public function redesSociales()
    {
        return $this->hasMany(RedSocial::class, 'contacto_id')->orderBy('orden');
    }
    
    public function horarios()
    {
        return $this->hasMany(HorarioAtencion::class, 'contacto_id')->orderBy('id');
    }
}
