<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifas', function (Blueprint $table) {
            $table->dropUnique('tarifas_categoria_vehiculo_unique');
            $table->unsignedSmallInteger('ejes')->default(2)->after('categoria_vehiculo');
            $table->unique(['categoria_vehiculo', 'ejes'], 'tarifas_categoria_ejes_unique');
        });
        DB::table('tarifas')->where('categoria_vehiculo', 'moto')->update(['ejes' => 1]);

        Schema::table('casetas', function (Blueprint $table) {
            $table->string('ubicacion')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('casetas', function (Blueprint $table) {
            $table->dropColumn('ubicacion');
        });

        Schema::table('tarifas', function (Blueprint $table) {
            $table->dropUnique('tarifas_categoria_ejes_unique');
            $table->dropColumn('ejes');
        });
        Schema::table('tarifas', function (Blueprint $table) {
            $table->unique('categoria_vehiculo');
        });
    }
};
