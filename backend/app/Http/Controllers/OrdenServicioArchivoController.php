<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArchivoResource;
use App\Models\Archivo;
use App\Models\OrdenServicio;
use App\Services\ArchivosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * La evidencia de una orden: fotos del equipo y la firma del cliente al
 * dejarlo y al retirarlo. Se cargan desde el detalle (en el celular, directo
 * con la cámara), aparte del formulario de la orden.
 */
class OrdenServicioArchivoController extends Controller
{
    public const MAX_FOTOS = 20;

    public const MOMENTOS = ['recepcion', 'entrega'];

    public function __construct(private ArchivosService $archivos) {}

    /** Suma fotos a las que ya tiene. */
    public function subirFotos(Request $request, OrdenServicio $orden): JsonResponse
    {
        $existentes = $orden->archivos()->pluck('id');

        $request->validate([
            'fotos' => ['required', 'array', 'min:1', 'max:'.max(1, self::MAX_FOTOS - $existentes->count())],
            // Las de un celular pesan: se aceptan hasta 10 MB y se achican al guardar.
            'fotos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],
        ], [
            'fotos.max' => 'Una orden admite hasta '.self::MAX_FOTOS.' fotos.',
        ], ['fotos' => 'fotos', 'fotos.*' => 'foto']);

        $items = [
            ...$existentes->map(fn ($id) => ['id' => $id])->all(),
            ...array_map(fn ($archivo) => ['archivo' => $archivo], $request->file('fotos')),
        ];
        $this->archivos->sincronizar($orden, $items, "ordenes-servicio/{$orden->id}");

        return response()->json(ArchivoResource::collection($orden->archivos()->get())->resolve($request), 201);
    }

    public function quitarFoto(Request $request, OrdenServicio $orden, Archivo $archivo): JsonResponse
    {
        abort_unless($archivo->archivable_type === $orden->getMorphClass() && (int) $archivo->archivable_id === $orden->id, 404);

        $items = $orden->archivos()->pluck('id')->reject(fn ($id) => $id === $archivo->id)->map(fn ($id) => ['id' => $id])->values()->all();
        $this->archivos->sincronizar($orden, $items, "ordenes-servicio/{$orden->id}");

        return response()->json(ArchivoResource::collection($orden->archivos()->get())->resolve($request));
    }

    /**
     * Guarda la firma (PNG en data URL, la dibuja el cliente en la pantalla).
     * Firmar de nuevo reemplaza la anterior.
     */
    public function firmar(Request $request, OrdenServicio $orden): JsonResponse
    {
        $datos = $request->validate([
            'momento' => ['required', Rule::in(self::MOMENTOS)],
            'imagen' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:700000'],
        ], [], ['momento' => 'momento', 'imagen' => 'firma']);

        $png = base64_decode(Str::after($datos['imagen'], 'base64,'), true);
        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw ValidationException::withMessages(['imagen' => 'La firma no es una imagen válida.']);
        }

        $columna = "firma_{$datos['momento']}";
        $disco = Storage::disk('public');
        $ruta = "ordenes-servicio/{$orden->id}/firma-{$datos['momento']}-".Str::random(12).'.png';
        $disco->put($ruta, $png);

        if ($orden->{$columna}) {
            $disco->delete($orden->{$columna});
        }
        $orden->forceFill([$columna => $ruta])->save();

        return response()->json([
            'momento' => $datos['momento'],
            'url' => $disco->url($ruta),
        ]);
    }
}
