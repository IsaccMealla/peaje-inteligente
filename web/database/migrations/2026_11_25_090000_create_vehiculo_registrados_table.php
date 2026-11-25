<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_registrados', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 15)->unique();
            $table->string('propietario')->nullable();
            $table->string('categoria')->default('Liviano');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_registrados');
    }
};
