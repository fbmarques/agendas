<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRecursoRequest;
use App\Http\Requests\Api\UpdateRecursoRequest;
use App\Models\Recurso;
use App\Models\RecursoUnidade;
use App\Models\Reserva;
use App\Models\User;
use App\Notifications\ReservaRecursoRemovido;
use App\Services\RecursoDisponibilidadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

    public function disponiveis(Request $request, RecursoDisponibilidadeService $svc): JsonResponse
    {
        $data = $request->validate([
            'ocorrencias' => ['required', 'array', 'min:1'],
            'ocorrencias.*.data_inicial' => ['required', 'date'],
            'ocorrencias.*.data_final' => ['required', 'date', 'after_or_equal:ocorrencias.*.data_inicial'],
            'ocorrencias.*.horario_inicial' => ['required', 'date_format:H:i'],
            'ocorrencias.*.horario_final' => ['required', 'date_format:H:i'],
        ]);

        $recursos = Recurso::with('disponibilidades')
            ->withCount('unidadesAtivas')
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get();

        $out = [];
        foreach ($recursos as $r) {
            $min = PHP_INT_MAX;
            foreach ($data['ocorrencias'] as $o) {
                $saldo = $svc->saldoNaJanela($r->id, $o['data_inicial'], $o['data_final'], $o['horario_inicial'], $o['horario_final']);
                if ($saldo < $min) $min = $saldo;
                if ($min <= 0) break;
            }
            if ($min > 0) {
                $out[] = [
                    'id' => (string) $r->id,
                    'nome' => $r->nome,
                    'saldo_minimo' => $min,
                ];
            }
        }

        return response()->json($out);
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

    public function previewRemocaoUnidade(Recurso $recurso, RecursoUnidade $unidade): JsonResponse
    {
        $this->authorize('update', $recurso);
        abort_unless($unidade->recurso_id === $recurso->id, 404);

        $qtdAntes = $recurso->quantidade;
        $qtdDepois = $unidade->status === 'ativo' ? max(0, $qtdAntes - 1) : $qtdAntes;

        $afetadas = $this->calcularReservasParaDesvincular($recurso, $qtdDepois, $unidade->id);

        return response()->json([
            'unidade' => [
                'id' => (string) $unidade->id,
                'patrimonio' => $unidade->patrimonio,
                'status' => $unidade->status,
            ],
            'resumo' => [
                'quantidade_antes' => $qtdAntes,
                'quantidade_depois' => $qtdDepois,
                'reservas_afetadas' => count($afetadas),
            ],
            'afetadas' => $afetadas,
        ]);
    }

    public function confirmarRemocaoUnidade(Request $request, Recurso $recurso, RecursoUnidade $unidade): JsonResponse
    {
        $this->authorize('update', $recurso);
        abort_unless($unidade->recurso_id === $recurso->id, 404);

        $data = $request->validate([
            'reserva_ids_desvincular' => ['array'],
            'reserva_ids_desvincular.*' => ['integer'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $ids = $data['reserva_ids_desvincular'] ?? [];
        $motivo = $data['motivo'] ?? null;

        $reservasNotificadas = [];
        DB::transaction(function () use ($recurso, $unidade, $ids, $motivo, &$reservasNotificadas) {
            $reservas = Reserva::whereIn('id', $ids)
                ->whereHas('recursos', fn ($q) => $q->where('recursos.id', $recurso->id))
                ->get();

            foreach ($reservas as $r) {
                $r->recursos()->detach($recurso->id);
                $reservasNotificadas[] = $r;
            }

            $unidade->update([
                'status' => 'inativo',
                'observacoes' => $motivo ?: $unidade->observacoes,
            ]);
        });

        foreach ($reservasNotificadas as $reserva) {
            $usuarios = collect([$reserva->user])->filter();
            if ($usuarios->isNotEmpty()) {
                Notification::send($usuarios, new ReservaRecursoRemovido($reserva, $recurso, $motivo));
            }
        }

        return response()->json([
            'unidade' => $unidade->fresh(),
            'desvinculadas' => count($reservasNotificadas),
        ]);
    }

    /**
     * Para uma nova quantidade hipotética, retorna a lista sugerida de
     * reservas que precisam perder o vínculo com o recurso. Sorteio
     * determinístico usando seed = unidadeId para o preview ser reproduzível.
     */
    private function calcularReservasParaDesvincular(Recurso $recurso, int $novaQtd, int $seedId): array
    {
        $reservas = Reserva::whereHas('recursos', fn ($q) => $q->where('recursos.id', $recurso->id))
            ->where('data_inicial', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelada')
            ->with(['user', 'local'])
            ->get()
            ->keyBy('id');

        if ($reservas->isEmpty()) return [];

        $qtdPorReserva = DB::table('reserva_recurso')
            ->where('recurso_id', $recurso->id)
            ->whereIn('reserva_id', $reservas->keys())
            ->pluck('quantidade', 'reserva_id');

        // Agrupa por slot idêntico (data + horário)
        $slots = [];
        foreach ($reservas as $r) {
            $key = "{$r->data_inicial->toDateString()}|{$r->data_final->toDateString()}|{$r->horario_inicial}|{$r->horario_final}";
            $slots[$key][] = $r;
        }

        mt_srand($seedId);
        $afetadas = [];
        foreach ($slots as $rs) {
            $total = 0;
            foreach ($rs as $r) $total += (int) ($qtdPorReserva[$r->id] ?? 1);
            if ($total <= $novaQtd) continue;

            $precisaLiberar = $total - $novaQtd;

            $ordem = collect($rs)->pluck('id')->all();
            // Fisher-Yates com mt_rand pra determinismo
            for ($i = count($ordem) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                [$ordem[$i], $ordem[$j]] = [$ordem[$j], $ordem[$i]];
            }

            $liberado = 0;
            foreach ($ordem as $id) {
                if ($liberado >= $precisaLiberar) break;
                $r = $reservas[$id];
                $q = (int) ($qtdPorReserva[$id] ?? 1);
                $afetadas[] = [
                    'reserva_id' => (string) $r->id,
                    'titulo' => $r->titulo,
                    'data_inicial' => $r->data_inicial->toDateString(),
                    'data_final' => $r->data_final->toDateString(),
                    'horario' => substr($r->horario_inicial, 0, 5).' — '.substr($r->horario_final, 0, 5),
                    'quantidade' => $q,
                    'usuario' => $r->user?->full_name ?? $r->responsavel_nome,
                    'local' => $r->local?->nome,
                ];
                $liberado += $q;
            }
        }

        return $afetadas;
    }
}
