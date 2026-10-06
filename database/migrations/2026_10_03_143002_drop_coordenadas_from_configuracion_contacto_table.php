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
        Schema::table('configuracion_contacto', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud']);
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_contacto', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
        });
    }
};
