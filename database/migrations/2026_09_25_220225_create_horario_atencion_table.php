<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('horario_atencion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contacto_id')
                  ->constrained('configuracion_contacto')
                  ->onDelete('cascade');
            $table->enum('dia_grupo', ['LUN_VIE', 'SABADO', 'DOMINGO']);
            $table->boolean('abierto')->default(false);
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->timestamps();
            $table->unique(['contacto_id', 'dia_grupo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horario_atencion');
    }
};
