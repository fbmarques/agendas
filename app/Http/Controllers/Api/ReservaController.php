<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BulkStoreReservaRequest;
use App\Http\Requests\Api\CancelarReservaRequest;
use App\Http\Requests\Api\StoreReservaRequest;
use App\Http\Requests\Api\UpdateReservaRequest;
use App\Models\Local;
use App\Models\Reserva;
use App\Notifications\ReservaCriada;
use App\Observers\ReservaObserver;
use App\Services\RecursoDisponibilidadeService;
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

    public function pendentes(Request $request): JsonResource
    {
        $user = $request->user();
        $query = Reserva::query()->pendentes();

        if (! $user->isAdmin()) {
            $ids = $user->locaisGerenciados()->pluck('locais.id');
            $query->whereIn('local_id', $ids);
        }

        return JsonResource::collection($query->orderBy('data_inicial')->get());
    }

    public function store(StoreReservaRequest $request, RecursoDisponibilidadeService $svc): JsonResponse
    {
        $this->authorize('create', Reserva::class);

        $data = $request->validated();
        $recursos = $data['recursos'] ?? [];
        unset($data['recursos']);

        // Verificar disponibilidade de cada recurso ANTES de criar a reserva
        foreach ($recursos as $r) {
            $chk = $svc->verificar(
                (int) $r['id'],
                (int) $r['quantidade'],
                $data['data_inicial'],
                $data['data_final'],
                $data['horario_inicial'],
                $data['horario_final'],
            );
            if (! $chk['ok']) {
                return response()->json(['message' => $chk['motivo']], 422);
            }
        }

        $data['user_id'] = $request->user()->id;
        $local = Local::findOrFail($data['local_id']);
        if (! array_key_exists('status', $data) || $data['status'] === null) {
            $data['status'] = $local->requer_aprovacao ? 'pendente' : 'confirmada';
        }

        $reserva = DB::transaction(function () use ($data, $recursos) {
            $r = Reserva::create($data);
            foreach ($recursos as $rec) {
                $r->recursos()->attach($rec['id'], ['quantidade' => (int) $rec['quantidade']]);
            }
            return $r;
        });

        if (! empty($recursos)) {
            (new ReservaObserver)->notificarRecursos($reserva->load('recursos.gerentes'), new ReservaCriada($reserva));
        }

        return response()->json($reserva->load('recursos'), 201);
    }

    public function bulk(BulkStoreReservaRequest $request, RecursoDisponibilidadeService $svc): JsonResponse
    {
        $this->authorize('create', Reserva::class);

        $items = $request->validated()['reservas'];
        $userId = $request->user()->id;
        $created = [];

        try {
            DB::transaction(function () use ($items, $userId, $svc, &$created) {
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

                    $indisp = Reserva::indisponibilidadeQueBloqueia(
                        $data['local_id'],
                        $data['data_inicial'],
                        $data['data_final'],
                        $data['horario_inicial'],
                        $data['horario_final'],
                    );
                    if ($indisp) {
                        $motivo = $indisp->motivo ?? 'Local indisponível';
                        throw new \RuntimeException(json_encode([
                            'index' => $index,
                            'message' => "Item {$index}: {$motivo}.",
                        ]));
                    }

                    $recursos = $data['recursos'] ?? [];
                    unset($data['recursos']);

                    foreach ($recursos as $rec) {
                        $chk = $svc->verificar(
                            (int) $rec['id'],
                            (int) $rec['quantidade'],
                            $data['data_inicial'],
                            $data['data_final'],
                            $data['horario_inicial'],
                            $data['horario_final'],
                        );
                        if (! $chk['ok']) {
                            throw new \RuntimeException(json_encode([
                                'index' => $index,
                                'message' => "Item {$index}: {$chk['motivo']}",
                            ]));
                        }
                    }

                    $data['user_id'] = $userId;

                    $local = Local::findOrFail($data['local_id']);
                    if (! array_key_exists('status', $data) || $data['status'] === null) {
                        $data['status'] = $local->requer_aprovacao ? 'pendente' : 'confirmada';
                    }

                    $reserva = Reserva::create($data);
                    foreach ($recursos as $rec) {
                        $reserva->recursos()->attach($rec['id'], ['quantidade' => (int) $rec['quantidade']]);
                    }
                    $created[] = $reserva->load('recursos');
                }
            });
        } catch (\RuntimeException $e) {
            $payload = json_decode($e->getMessage(), true);
            return response()->json([
                'message' => $payload['message'] ?? $e->getMessage(),
                'conflict_index' => $payload['index'] ?? null,
            ], 422);
        }

        $observer = new ReservaObserver;
        foreach ($created as $r) {
            if ($r->recursos->isNotEmpty()) {
                $observer->notificarRecursos($r->load('recursos.gerentes'), new ReservaCriada($r));
            }
        }

        return response()->json([
            'created_count' => count($created),
            'reservas' => $created,
        ], 201);
    }

    public function show(Reserva $reserva): JsonResource
    {
        return new JsonResource($reserva->load('recursos'));
    }

    public function update(UpdateReservaRequest $request, Reserva $reserva): JsonResource
    {
        $this->authorize('update', $reserva);

        $reserva->update($request->validated());

        return new JsonResource($reserva->fresh('recursos'));
    }

    public function aprovar(Request $request, Reserva $reserva): JsonResource
    {
        $this->authorize('aprovar', $reserva);

        $reserva->update([
            'status' => 'confirmada',
            'aprovada_por_id' => $request->user()->id,
            'aprovada_em' => now(),
        ]);

        return new JsonResource($reserva->fresh('recursos'));
    }

    public function cancelar(CancelarReservaRequest $request, Reserva $reserva): JsonResource
    {
        $this->authorize('cancelar', $reserva);

        $reserva->update([
            'status' => 'cancelada',
            'motivo_cancelamento' => $request->validated('motivo_cancelamento'),
            'cancelada_por_id' => $request->user()->id,
            'cancelada_em' => now(),
        ]);

        return new JsonResource($reserva->fresh('recursos'));
    }

    public function destroy(Reserva $reserva): JsonResponse
    {
        $this->authorize('delete', $reserva);

        $reserva->delete();

        return response()->json(null, 204);
    }
}
