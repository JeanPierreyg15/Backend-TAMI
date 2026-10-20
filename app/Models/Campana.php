<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campana extends Model
{
    // Permitimos que estos campos se puedan llenar masivamente
    protected $table = "campanas";
    protected $fillable = [
    'nombre', 
    'producto_id',
    'contenido_personalizado', 
    'imagen_path'];

    // Relación: Una campaña pertenece a un producto
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    // Relación: Una campaña tiene muchos logs de mensajes
    public function messageLogs()
    {
        return $this->hasMany(CampaignMessageLog::class, 'campana_id');
    }
}
