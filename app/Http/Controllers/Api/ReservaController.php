<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BulkStoreReservaRequest;
use App\Http\Requests\Api\StoreReservaRequest;
use App\Http\Requests\Api\UpdateReservaRequest;
use App\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ReservaController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $query = Reserva::query();

        if ($id = $request->query('campi_id')) $query->where('campi_id', $id);
        if ($id = $request->query('grupo_id')) $query->where('grupo_id', $id);
        if ($id = $request->query('local_id')) $query->where('local_id', $id);
        if ($from = $request->query('from')) $query->where('data_final', '>=', $from);
        if ($to = $request->query('to')) $query->where('data_inicial', '<=', $to);

        return JsonResource::collection($query->orderBy('data_inicial')->get());
    }

    public function minhas(Request $request): JsonResource
    {
        $query = Reserva::query()->where('user_id', $request->user()->id);
        if ($id = $request->query('campi_id')) $query->where('campi_id', $id);
        if ($status = $request->query('status')) $query->where('status', $status);

        return JsonResource::collection($query->orderByDesc('data_inicial')->get());
    }

    public function store(StoreReservaRequest $request): JsonResponse
    {
        $this->authorize('create', Reserva::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $reserva = Reserva::create($data);

        return response()->json($reserva, 201);
    }

    public function bulk(BulkStoreReservaRequest $request): JsonResponse
    {
        $this->authorize('create', Reserva::class);

        $items = $request->validated()['reservas'];
        $userId = $request->user()->id;
        $created = [];

        try {
            DB::transaction(function () use ($items, $userId, &$created) {
                foreach ($items as $index => $data) {
                    $conflito = Reserva::conflitos(
                        $data['local_id'],
                        $data['data_inicial'],
                        $data['data_final'],
                        $data['horario_inicial'],
                        $data['horario_final'],
                    )->first();

                    if ($conflito) {
                        throw new \RuntimeException(json_encode([
                            'index' => $index,
                            'message' => "Conflito no item {$index}: {$conflito->titulo}.",
                        ]));
                    }

                    $data['user_id'] = $userId;
                    $created[] = Reserva::create($data);
                }
            });
        } catch (\RuntimeException $e) {
            $payload = json_decode($e->getMessage(), true);
            return response()->json([
                'message' => $payload['message'] ?? $e->getMessage(),
                'conflict_index' => $payload['index'] ?? null,
            ], 422);
        }

        return response()->json([
            'created_count' => count($created),
            'reservas' => $created,
        ], 201);
    }

    public function show(Reserva $reserva): JsonResource
    {
        return new JsonResource($reserva);
    }

    public function update(UpdateReservaRequest $request, Reserva $reserva): JsonResource
    {
        $this->authorize('update', $reserva);

        $reserva->update($request->validated());

        return new JsonResource($reserva->fresh());
    }

    public function destroy(Reserva $reserva): JsonResponse
    {
        $this->authorize('delete', $reserva);

        $reserva->delete();

        return response()->json(null, 204);
    }
}
