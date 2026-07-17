<?php

namespace Database\Seeders;

use App\Models\Campi;
use App\Models\Grupo;
use App\Models\Local;
use App\Models\LocalIndisponibilidade;
use App\Models\Periodo;
use App\Models\Recurso;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        mt_srand(42); // seed determinístico

        $campisDef = [
            ['nome' => 'Campus JK — Diamantina', 'sigla' => 'JK', 'cidade' => 'Diamantina'],
            ['nome' => 'Campus Mucuri', 'sigla' => 'MUC', 'cidade' => 'Teófilo Otoni'],
            ['nome' => 'Campus Unaí', 'sigla' => 'UNA', 'cidade' => 'Unaí'],
            ['nome' => 'Campus Janaúba', 'sigla' => 'JAN', 'cidade' => 'Janaúba'],
            ['nome' => 'Campus Teófilo Otoni', 'sigla' => 'TO', 'cidade' => 'Teófilo Otoni'],
        ];

        $tipos = array_map(fn ($t) => $t['nome'], config('tipos_local'));

        $grupos = ['Bloco A', 'Bloco B', 'Laboratórios'];

        $usuarios = $this->criarUsuarios();
        $this->command?->info('Usuários: ' . count($usuarios));

        $periodos = $this->criarPeriodos();
        $this->command?->info('Períodos: ' . count($periodos));

        $recursos = $this->criarRecursos();
        $this->command?->info('Recursos: ' . count($recursos));

        $locaisComAprovacao = [];
        $todosLocais = [];

        foreach ($campisDef as $cdef) {
            $campi = Campi::updateOrCreate(
                ['sigla' => $cdef['sigla']],
                ['nome' => $cdef['nome'], 'cidade' => $cdef['cidade'], 'status' => 'ativo'],
            );

            $gruposDoCampi = [];
            foreach ($grupos as $nomeGrupo) {
                $gruposDoCampi[] = Grupo::firstOrCreate(
                    ['campi_id' => $campi->id, 'nome' => $nomeGrupo],
                    ['status' => 'ativo'],
                );
            }

            // Idempotência: só cria locais se o campi ainda não tem
            $existentes = Local::where('campi_id', $campi->id)->count();
            if ($existentes > 0) {
                $todosLocais = array_merge($todosLocais, Local::where('campi_id', $campi->id)->get()->all());
                foreach (Local::where('campi_id', $campi->id)->where('requer_aprovacao', true)->get() as $l) {
                    $locaisComAprovacao[] = $l;
                }
                continue;
            }

            $qtd = mt_rand(10, 15);
            for ($i = 1; $i <= $qtd; $i++) {
                $grupo = $gruposDoCampi[array_rand($gruposDoCampi)];
                $tipo = $tipos[array_rand($tipos)];
                $requerAprovacao = mt_rand(1, 100) <= 30;

                $local = Local::create([
                    'campi_id' => $campi->id,
                    'grupo_id' => $grupo->id,
                    'nome' => "{$tipo} {$campi->sigla}-{$i}",
                    'tipo' => $tipo,
                    'capacidade' => mt_rand(20, 120),
                    'descricao' => "Espaço {$i} do {$campi->nome}",
                    'recursos' => 'Projetor, Ar condicionado',
                    'status' => 'ativo',
                    'requer_aprovacao' => $requerAprovacao,
                ]);

                $todosLocais[] = $local;
                if ($requerAprovacao) $locaisComAprovacao[] = $local;
            }
        }

        $this->command?->info('Locais totais: ' . count($todosLocais) . ' (com aprovação: ' . count($locaisComAprovacao) . ')');

        // Gerentes: 1-3 usuários por local que exige aprovação
        foreach ($locaisComAprovacao as $local) {
            if ($local->gerentes()->exists()) continue;
            $n = mt_rand(1, 3);
            $ids = collect($usuarios)->random(min($n, count($usuarios)))->pluck('id')->all();
            $local->gerentes()->syncWithoutDetaching($ids);
        }

        // Indisponibilidades (1-2 por campi)
        foreach ($campisDef as $cdef) {
            $campi = Campi::where('sigla', $cdef['sigla'])->first();
            $primeiroLocal = Local::where('campi_id', $campi->id)->first();
            if (! $primeiroLocal) continue;

            LocalIndisponibilidade::firstOrCreate(
                ['local_id' => $primeiroLocal->id, 'tipo' => 'data_especifica', 'data_inicial' => '2026-09-07'],
                ['motivo' => 'Feriado — Independência'],
            );
            LocalIndisponibilidade::firstOrCreate(
                ['local_id' => $primeiroLocal->id, 'tipo' => 'recorrente_semanal', 'dias_semana' => [0]],
                ['motivo' => 'Fechado aos domingos'],
            );
        }

        // Reservas de exemplo (só se não houver nenhuma ainda)
        if (Reserva::count() === 0) {
            $this->criarReservas($todosLocais, $usuarios, $recursos);
        }
    }

    private function criarUsuarios(): array
    {
        $primeiros = ['ana', 'bruno', 'carla', 'diego', 'elisa', 'fabio', 'gabriela', 'hugo', 'isabela', 'julio', 'karla', 'lucas', 'marta', 'nelson', 'olivia', 'paulo', 'quezia', 'ricardo', 'sofia', 'tiago'];
        $sobrenomes = ['silva', 'souza', 'oliveira', 'pereira', 'costa', 'rodrigues', 'almeida', 'nunes', 'ferreira', 'gomes', 'martins', 'ribeiro', 'carvalho', 'lima', 'araujo'];
        $out = [];
        $vistos = [];
        while (count($out) < 30) {
            $p = $primeiros[array_rand($primeiros)];
            $s = $sobrenomes[array_rand($sobrenomes)];
            $email = "{$p}.{$s}@local.com";
            if (isset($vistos[$email])) continue;
            $vistos[$email] = true;

            $u = User::updateOrCreate(
                ['email' => $email],
                [
                    'full_name' => ucfirst($p) . ' ' . ucfirst($s),
                    'role' => 'user',
                    'password' => Hash::make('12345678'),
                    'email_verified_at' => now(),
                ],
            );
            $out[] = $u;
        }
        return $out;
    }

    private function criarPeriodos(): array
    {
        return [
            Periodo::updateOrCreate(
                ['nome' => '2026/1'],
                ['data_inicio' => '2026-02-16', 'data_fim' => '2026-07-04', 'status' => 'ativo'],
            ),
            Periodo::updateOrCreate(
                ['nome' => '2026/2'],
                ['data_inicio' => '2026-08-03', 'data_fim' => '2026-12-19', 'status' => 'ativo'],
            ),
        ];
    }

    private function criarRecursos(): array
    {
        $defs = [
            ['nome' => 'Som', 'quantidade' => 3, 'email' => 'som.responsavel@local.com', 'resp' => 'Ana Silva'],
            ['nome' => 'Copa', 'quantidade' => 2, 'email' => 'copa.responsavel@local.com', 'resp' => 'Bruno Souza'],
            ['nome' => 'Projetor extra', 'quantidade' => 5, 'email' => 'projetor.responsavel@local.com', 'resp' => 'Carla Oliveira'],
            ['nome' => 'Técnico de laboratório', 'quantidade' => 2, 'email' => 'tecnico.lab@local.com', 'resp' => 'Diego Pereira'],
            ['nome' => 'Câmera de vídeo', 'quantidade' => 2, 'email' => 'camera.responsavel@local.com', 'resp' => 'Elisa Costa'],
        ];
        $out = [];
        foreach ($defs as $d) {
            $r = Recurso::updateOrCreate(
                ['nome' => $d['nome']],
                [
                    'responsavel_nome' => $d['resp'],
                    'responsavel_email' => $d['email'],
                    'status' => 'ativo',
                ],
            );
            if ($r->disponibilidades()->count() === 0) {
                $r->disponibilidades()->create(['dias_semana' => [1,2,3,4,5], 'horario_inicial' => '08:00', 'horario_final' => '12:00']);
                $r->disponibilidades()->create(['dias_semana' => [1,2,3,4,5], 'horario_inicial' => '14:00', 'horario_final' => '18:00']);
            }
            if ($r->unidades()->count() === 0) {
                $prefixo = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $d['nome']), 0, 4));
                for ($n = 1; $n <= $d['quantidade']; $n++) {
                    $r->unidades()->create([
                        'patrimonio' => sprintf('%s-%03d', $prefixo, $n),
                        'status' => 'ativo',
                    ]);
                }
            }
            $out[] = $r;
        }
        return $out;
    }

    private function criarReservas(array $locais, array $usuarios, array $recursos): void
    {
        if (empty($locais) || empty($usuarios)) return;

        $motivosBase = [
            'Aula da disciplina de programação para a turma do primeiro semestre com duração completa da manhã',
            'Reunião de planejamento pedagógico do curso para alinhamento das próximas ações do semestre letivo',
            'Palestra sobre iniciação científica aberta a toda a comunidade acadêmica com foco em bolsas ativas',
            'Encontro de estudos coletivos para o grupo de monitoria da disciplina com duração de duas horas seguidas',
            'Defesa de trabalho de conclusão de curso com participação da banca examinadora completa presencial',
        ];

        $horarios = [
            ['08:00', '10:00'],
            ['10:00', '12:00'],
            ['14:00', '16:00'],
            ['16:00', '18:00'],
        ];

        for ($i = 0; $i < 20; $i++) {
            $local = $locais[array_rand($locais)];
            $usuario = $usuarios[array_rand($usuarios)];
            $motivo = $motivosBase[array_rand($motivosBase)];
            $h = $horarios[array_rand($horarios)];

            // Data futura: entre 7 e 90 dias após "hoje" (2026-07-15)
            $data = now()->addDays(mt_rand(7, 90))->format('Y-m-d');

            // Domingo é bloqueado pelo indisponibilidade padrão do primeiro local de cada campi — evita
            $dow = date('w', strtotime($data));
            if ($dow == 0) $data = date('Y-m-d', strtotime($data . ' +1 day'));

            $conflito = Reserva::conflitos($local->id, $data, $data, $h[0], $h[1])->exists();
            if ($conflito) continue;

            $status = $local->requer_aprovacao ? 'pendente' : 'confirmada';

            $r = Reserva::create([
                'user_id' => $usuario->id,
                'campi_id' => $local->campi_id,
                'grupo_id' => $local->grupo_id,
                'local_id' => $local->id,
                'titulo' => "Reserva demo #".($i+1),
                'motivo' => $motivo,
                'tipo_local' => $local->tipo,
                'data_inicial' => $data,
                'data_final' => $data,
                'horario_inicial' => $h[0],
                'horario_final' => $h[1],
                'responsavel_nome' => $usuario->full_name,
                'status' => $status,
                'recorrente' => false,
            ]);

            // 30% de chance de anexar 1 recurso
            if (mt_rand(1, 100) <= 30 && ! empty($recursos)) {
                $rec = $recursos[array_rand($recursos)];
                // Só anexa se estiver dentro da janela seg-sex 08-12 ou 14-18 (recursos configurados assim)
                if ($dow >= 1 && $dow <= 5) {
                    $dentroJanela = ($h[0] >= '08:00' && $h[1] <= '12:00') || ($h[0] >= '14:00' && $h[1] <= '18:00');
                    if ($dentroJanela) {
                        $r->recursos()->attach($rec->id, ['quantidade' => 1]);
                    }
                }
            }
        }
    }
}
