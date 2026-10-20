<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessageLog extends Model
{
    protected $table = 'campaign_message_logs';

    protected $fillable = [
        'campana_id',
        'cliente_id',
        'phone',
        'status',
        'error_message',
    ];

    /**
     * Relación con la campaña
     */
    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class, 'campana_id');
    }

    /**
     * Relación con el cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
