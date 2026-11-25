<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casetas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->default('Caseta principal');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::table('registros', function (Blueprint $table) {
            $table->unsignedInteger('pasajeros')->default(0);
            $table->foreignId('caseta_id')->nullable()->constrained('casetas')->nullOnDelete();
        });

        Schema::table('vehiculo_registrados', function (Blueprint $table) {
            $table->decimal('saldo', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('vehiculo_registrados', function (Blueprint $table) {
            $table->dropColumn('saldo');
        });

        Schema::table('registros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caseta_id');
            $table->dropColumn('pasajeros');
        });

        Schema::dropIfExists('casetas');
    }
};
