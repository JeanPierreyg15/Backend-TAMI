<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RedSocial extends Model
{
    protected $table = 'red_social';
    protected $fillable = [
        'contacto_id',
        'tipo',
        'nombre',
        'url',
        'orden',
    ];

    public function contacto()
    {
        return $this->belongsTo(ConfiguracionContacto::class, 'contacto_id');
    }

}
