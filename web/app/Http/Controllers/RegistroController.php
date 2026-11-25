<?php

namespace App\Http\Controllers;

use App\Models\Registro;
use Illuminate\Support\Facades\Storage;

class RegistroController extends Controller
{
    public function captura(Registro $registro)
    {
        $prefijoDataUri = 'data:image/jpeg;base64,';

        if (! str_starts_with($registro->imagen_url, $prefijoDataUri)) {
            abort_unless(Storage::disk('public')->exists($registro->imagen_url), 404);

            return Storage::disk('public')->response($registro->imagen_url);
        }

        $contenido = base64_decode(substr($registro->imagen_url, strlen($prefijoDataUri)), true);
        abort_if($contenido === false, 404);

        return response($contenido, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
