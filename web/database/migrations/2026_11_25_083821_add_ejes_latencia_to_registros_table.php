<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registros', function (Blueprint $table) {
            $table->integer('ejes')->default(2)->after('clase_vehiculo');
            $table->float('latencia_ms')->nullable()->after('confianza');
        });
    }

    public function down(): void
    {
        Schema::table('registros', function (Blueprint $table) {
            $table->dropColumn(['ejes', 'latencia_ms']);
        });
    }
};
