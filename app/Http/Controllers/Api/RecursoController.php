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

    public function relatorioReservas(Recurso $recurso, Request $request)
    {
        $this->authorize('view', $recurso);
        [$di, $df, $format] = $this->relatorioParams($request);

        $rows = DB::table('reserva_recurso as rr')
            ->join('reservas as r', 'r.id', '=', 'rr.reserva_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('locais as l', 'l.id', '=', 'r.local_id')
            ->where('rr.recurso_id', $recurso->id)
            ->where('r.status', '!=', 'cancelada')
            ->where('r.data_inicial', '<=', $df)
            ->where('r.data_final', '>=', $di)
            ->orderBy('r.data_inicial')->orderBy('r.horario_inicial')
            ->get([
                'r.id as reserva_id', 'r.titulo',
                'r.data_inicial', 'r.data_final',
                'r.horario_inicial', 'r.horario_final',
                'rr.quantidade',
                DB::raw('COALESCE(u.full_name, r.responsavel_nome) as usuario'),
                'l.nome as local',
            ])
            ->map(fn ($row) => [
                'reserva_id' => (string) $row->reserva_id,
                'titulo' => $row->titulo,
                'data_inicial' => $row->data_inicial,
                'data_final' => $row->data_final,
                'horario' => substr($row->horario_inicial, 0, 5).' — '.substr($row->horario_final, 0, 5),
                'quantidade' => (int) $row->quantidade,
                'usuario' => $row->usuario,
                'local' => $row->local,
            ]);

        if ($format === 'csv') {
            return $this->csvStream("recurso-{$recurso->id}-reservas.csv",
                ['Reserva', 'Título', 'Data inicial', 'Data final', 'Horário', 'Quantidade', 'Usuário', 'Local'],
                $rows->map(fn ($r) => [$r['reserva_id'], $r['titulo'], $r['data_inicial'], $r['data_final'], $r['horario'], $r['quantidade'], $r['usuario'], $r['local']]),
            );
        }

        return response()->json(['periodo' => ['inicio' => $di, 'fim' => $df], 'linhas' => $rows]);
    }

    public function relatorioOcupacao(Recurso $recurso, Request $request)
    {
        $this->authorize('view', $recurso);
        [$di, $df, $format] = $this->relatorioParams($request);

        $recurso->load('disponibilidades');
        $qtdAtiva = $recurso->quantidade;

        // Horas disponíveis: para cada dia do intervalo, some as horas das janelas do dia da semana * qtd
        $iter = strtotime($di);
        $end = strtotime($df);
        $porMes = [];
        while ($iter <= $end) {
            $ym = date('Y-m', $iter);
            $dow = (int) date('w', $iter);
            $porMes[$ym] ??= ['mes' => $ym, 'horas_disponiveis' => 0.0, 'horas_reservadas' => 0.0];

            foreach ($recurso->disponibilidades as $d) {
                $dias = is_array($d->dias_semana) ? $d->dias_semana : [];
                if (! in_array($dow, $dias, true)) continue;
                $porMes[$ym]['horas_disponiveis'] += $this->diffHoras($d->horario_inicial, $d->horario_final) * max(1, $qtdAtiva);
            }

            $iter += 86400;
        }

        // Horas reservadas por mês
        $reservas = DB::table('reserva_recurso as rr')
            ->join('reservas as r', 'r.id', '=', 'rr.reserva_id')
            ->where('rr.recurso_id', $recurso->id)
            ->where('r.status', '!=', 'cancelada')
            ->where('r.data_inicial', '<=', $df)
            ->where('r.data_final', '>=', $di)
            ->get(['r.data_inicial', 'r.data_final', 'r.horario_inicial', 'r.horario_final', 'rr.quantidade']);
        foreach ($reservas as $r) {
            $dur = $this->diffHoras($r->horario_inicial, $r->horario_final);
            $t = max(strtotime($r->data_inicial), strtotime($di));
            $fim = min(strtotime($r->data_final), $end);
            while ($t <= $fim) {
                $ym = date('Y-m', $t);
                $porMes[$ym] ??= ['mes' => $ym, 'horas_disponiveis' => 0.0, 'horas_reservadas' => 0.0];
                $porMes[$ym]['horas_reservadas'] += $dur * (int) $r->quantidade;
                $t += 86400;
            }
        }

        ksort($porMes);
        $linhas = array_map(function ($m) {
            $pct = $m['horas_disponiveis'] > 0 ? round(100 * $m['horas_reservadas'] / $m['horas_disponiveis'], 1) : null;
            return [
                'mes' => $m['mes'],
                'horas_disponiveis' => round($m['horas_disponiveis'], 2),
                'horas_reservadas' => round($m['horas_reservadas'], 2),
                'ocupacao_pct' => $pct,
                'sobrealocado' => $pct !== null && $pct > 100,
            ];
        }, array_values($porMes));

        if ($format === 'csv') {
            return $this->csvStream("recurso-{$recurso->id}-ocupacao.csv",
                ['Mês', 'Horas disponíveis', 'Horas reservadas', 'Ocupação (%)', 'Sobrealocado?'],
                collect($linhas)->map(fn ($l) => [$l['mes'], $l['horas_disponiveis'], $l['horas_reservadas'], $l['ocupacao_pct'] ?? '', $l['sobrealocado'] ? 'sim' : 'não']),
            );
        }

        return response()->json(['periodo' => ['inicio' => $di, 'fim' => $df], 'linhas' => $linhas]);
    }

    public function relatorioUnidades(Recurso $recurso, Request $request)
    {
        $this->authorize('view', $recurso);
        [$di, $df, $format] = $this->relatorioParams($request);

        $unidades = $recurso->unidades()->orderBy('patrimonio')->get();
        $qtdAtiva = $unidades->where('status', 'ativo')->count();

        $totalHorasRecurso = DB::table('reserva_recurso as rr')
            ->join('reservas as r', 'r.id', '=', 'rr.reserva_id')
            ->where('rr.recurso_id', $recurso->id)
            ->where('r.status', '!=', 'cancelada')
            ->where('r.data_inicial', '<=', $df)
            ->where('r.data_final', '>=', $di)
            ->get(['r.data_inicial', 'r.data_final', 'r.horario_inicial', 'r.horario_final', 'rr.quantidade'])
            ->sum(function ($r) use ($di, $df) {
                $dias = 0;
                $t = max(strtotime($r->data_inicial), strtotime($di));
                $fim = min(strtotime($r->data_final), strtotime($df));
                while ($t <= $fim) { $dias++; $t += 86400; }
                return $this->diffHoras($r->horario_inicial, $r->horario_final) * $dias * (int) $r->quantidade;
            });

        $porUnidade = $qtdAtiva > 0 ? $totalHorasRecurso / $qtdAtiva : 0;

        $linhas = $unidades->map(fn ($u) => [
            'unidade_id' => (string) $u->id,
            'patrimonio' => $u->patrimonio,
            'status' => $u->status,
            'horas_alocadas_estimadas' => $u->status === 'ativo' ? round($porUnidade, 2) : 0,
            'observacoes' => $u->observacoes,
        ])->all();

        if ($format === 'csv') {
            return $this->csvStream("recurso-{$recurso->id}-unidades.csv",
                ['Patrimônio', 'Status', 'Horas alocadas (estimado)', 'Observações'],
                collect($linhas)->map(fn ($l) => [$l['patrimonio'], $l['status'], $l['horas_alocadas_estimadas'], $l['observacoes']]),
            );
        }

        return response()->json([
            'periodo' => ['inicio' => $di, 'fim' => $df],
            'total_horas_recurso' => round($totalHorasRecurso, 2),
            'nota' => 'Horas por unidade são estimadas: dividem-se as horas totais igualmente entre unidades ativas.',
            'linhas' => $linhas,
        ]);
    }

    private function relatorioParams(Request $request): array
    {
        $data = $request->validate([
            'data_inicial' => ['nullable', 'date'],
            'data_final' => ['nullable', 'date'],
            'format' => ['nullable', 'in:json,csv'],
        ]);
        $di = $data['data_inicial'] ?? now()->toDateString();
        $df = $data['data_final'] ?? now()->addDays(90)->toDateString();
        $format = $data['format'] ?? 'json';
        return [$di, $df, $format];
    }

    private function diffHoras(string $hi, string $hf): float
    {
        $a = strtotime("1970-01-01 {$hi}");
        $b = strtotime("1970-01-01 {$hf}");
        return max(0, ($b - $a) / 3600);
    }

    private function csvStream(string $filename, array $header, $rows)
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM utf-8 para Excel
            fputcsv($out, $header);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
