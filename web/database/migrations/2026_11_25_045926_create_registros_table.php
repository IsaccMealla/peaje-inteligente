<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('tarifa_id')->nullable()->constrained('tarifas')->nullOnDelete();
            $table->string('placa_detectada')->nullable();
            $table->string('clase_vehiculo')->nullable();
            $table->float('confianza')->default(0);
            $table->string('imagen_url');
            $table->string('estado')->default('procesado'); // procesado, revision, rechazado
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros');
    }
};