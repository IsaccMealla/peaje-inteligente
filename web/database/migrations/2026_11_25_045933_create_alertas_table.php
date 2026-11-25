<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registro_id')->nullable()->constrained('registros')->cascadeOnDelete();
            $table->string('tipo'); // baja_confianza, placa_no_legible, vehiculo_restringido
            $table->text('mensaje');
            $table->boolean('leida')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};