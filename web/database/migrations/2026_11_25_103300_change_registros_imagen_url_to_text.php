<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registros', function (Blueprint $table) {
            $table->text('imagen_url')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('registros')->where('imagen_url', 'like', 'data:image/%')->exists()) {
            throw new RuntimeException(
                'Cannot reduce registros.imagen_url to varchar while database image data exists.'
            );
        }

        Schema::table('registros', function (Blueprint $table) {
            $table->string('imagen_url')->change();
        });
    }
};
