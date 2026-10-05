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
        Schema::create('red_social', function (Blueprint $table) {
        $table->id();
        $table->foreignId('contacto_id')
              ->constrained('configuracion_contacto')
              ->onDelete('cascade');
        $table->enum('tipo', ['instagram', 'facebook', 'twitter', 'youtube', 'otra']);
        $table->string('nombre', 50)->nullable();
        $table->string('url', 300);
        $table->integer('orden')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('red_social');
    }
};
