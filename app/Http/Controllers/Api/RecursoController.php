<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRecursoRequest;
use App\Http\Requests\Api\UpdateRecursoRequest;
use App\Models\Recurso;
use App\Models\RecursoUnidade;
use App\Services\RecursoDisponibilidadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecursoController extends Controller
{
    public function index(): JsonResource
    {
        return JsonResource::collection(
            Recurso::with('disponibilidades', 'unidades')
                ->withCount(['unidadesAtivas'])
                ->orderBy('nome')
                ->get(),
        );
    }

    public function show(Recurso $recurso): JsonResource
    {
        return new JsonResource($recurso->load('disponibilidades', 'unidades')->loadCount('unidadesAtivas'));
    }

    public function store(StoreRecursoRequest $request): JsonResponse
    {
        $this->authorize('create', Recurso::class);
        $data = $request->validated();
        $disp = $data['disponibilidades'] ?? [];
        $unidades = $data['unidades'] ?? [];
        unset($data['disponibilidades'], $data['unidades']);

        $recurso = DB::transaction(function () use ($data, $disp, $unidades) {
            $r = Recurso::create($data);
            foreach ($disp as $d) {
                $r->disponibilidades()->create($d);
            }
            foreach ($unidades as $u) {
                $r->unidades()->create([
                    'patrimonio' => $u['patrimonio'],
                    'observacoes' => $u['observacoes'] ?? null,
                    'status' => 'ativo',
                ]);
            }
            return $r;
        });

        return response()->json(
            $recurso->load('disponibilidades', 'unidades')->loadCount('unidadesAtivas'),
            201,
        );
    }

    public function update(UpdateRecursoRequest $request, Recurso $recurso): JsonResource
    {
        $this->authorize('update', $recurso);
        $data = $request->validated();
        $disp = $data['disponibilidades'] ?? null;
        unset($data['disponibilidades']);

        DB::transaction(function () use ($recurso, $data, $disp) {
            $recurso->update($data);
            if (is_array($disp)) {
                $recurso->disponibilidades()->delete();
                foreach ($disp as $d) {
                    $recurso->disponibilidades()->create($d);
                }
            }
        });

        return new JsonResource($recurso->fresh(['disponibilidades', 'unidades'])->loadCount('unidadesAtivas'));
    }

    public function destroy(Recurso $recurso): JsonResponse
    {
        $this->authorize('delete', $recurso);
        $recurso->delete();
        return response()->json(null, 204);
    }

    public function verificarDisponibilidade(Request $request, Recurso $recurso, RecursoDisponibilidadeService $svc): JsonResponse
    {
        $this->authorize('view', $recurso);

        $data = $request->validate([
            'quantidade' => ['required', 'integer', 'min:1'],
            'data_inicial' => ['required', 'date'],
            'data_final' => ['required', 'date', 'after_or_equal:data_inicial'],
            'horario_inicial' => ['required', 'date_format:H:i'],
            'horario_final' => ['required', 'date_format:H:i', 'after:horario_inicial'],
            'ignorar_reserva_id' => ['nullable', 'integer'],
        ]);

        $r = $svc->verificar(
            $recurso->id,
            (int) $data['quantidade'],
            $data['data_inicial'],
            $data['data_final'],
            $data['horario_inicial'],
            $data['horario_final'],
            $data['ignorar_reserva_id'] ?? null,
        );

        return response()->json($r);
    }

    public function agenda(Recurso $recurso): JsonResource
    {
        $this->authorize('verAgenda', $recurso);

        $reservas = $recurso->reservas()
            ->where('reservas.status', '!=', 'cancelada')
            ->orderBy('reservas.data_inicial')
            ->get();

        return JsonResource::collection($reservas);
    }

    public function listarUnidades(Recurso $recurso): JsonResource
    {
        $this->authorize('view', $recurso);
        return JsonResource::collection($recurso->unidades()->orderBy('patrimonio')->get());
    }

    public function criarUnidade(Request $request, Recurso $recurso): JsonResponse
    {
        $this->authorize('update', $recurso);

        $data = $request->validate([
            'patrimonio' => [
                'required', 'string', 'max:60',
                Rule::unique('recurso_unidades', 'patrimonio')->where('recurso_id', $recurso->id),
            ],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ]);

        $unidade = $recurso->unidades()->create([
            'patrimonio' => $data['patrimonio'],
            'observacoes' => $data['observacoes'] ?? null,
            'status' => 'ativo',
        ]);

        return response()->json($unidade, 201);
    }

    public function atualizarUnidade(Request $request, Recurso $recurso, RecursoUnidade $unidade): JsonResource
    {
        $this->authorize('update', $recurso);
        abort_unless($unidade->recurso_id === $recurso->id, 404);

        $data = $request->validate([
            'patrimonio' => [
                'sometimes', 'required', 'string', 'max:60',
                Rule::unique('recurso_unidades', 'patrimonio')
                    ->where('recurso_id', $recurso->id)
                    ->ignore($unidade->id),
            ],
            'status' => ['sometimes', 'in:ativo,inativo'],
            'observacoes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $unidade->update($data);
        return new JsonResource($unidade->fresh());
    }
}
