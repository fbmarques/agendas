import { useState, useMemo, useEffect } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { TimeInput } from "@/components/ui/time-input";
import { AlertTriangle, CheckCircle2, Lock } from "lucide-react";
import { base44 } from "@/api/base44Client";
import { getCorTipo } from "@/lib/tiposLocal";
import { parseISO, eachDayOfInterval, getDay, format as fnsFormat } from "date-fns";
import { ptBR } from "date-fns/locale";
import RecurringDaysPicker from "@/components/RecurringDaysPicker";

export default function ReservationModal({ open, onClose, onCreated, campi, grupos, locais, reservasExistentes = [], preCampiId, preGrupoId, preLocalId, preData }) {
  const [form, setForm] = useState({
    titulo: "", motivo: "", observacoes: "",
    campi_id: preCampiId || "", grupo_id: preGrupoId || "", local_id: preLocalId || "",
    data_inicial: preData || "", data_final: preData || "",
    horario_inicial: "", horario_final: "",
  });
  const [tipoReserva, setTipoReserva] = useState("unica");
  const [diasRecorrente, setDiasRecorrente] = useState({});
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(false);
  const [periodos, setPeriodos] = useState([]);
  const [recursosDisponiveis, setRecursosDisponiveis] = useState([]);
  const [recursosSelecionados, setRecursosSelecionados] = useState({});
  const [erroEnvio, setErroEnvio] = useState(null);

  const locked = !!preLocalId;

  useEffect(() => {
    base44.auth.me().then(setUser).catch(() => {});
    base44.entities.Periodo.list()
      .then((list) => setPeriodos((list || []).filter((p) => p.status === "ativo")))
      .catch(() => {});
  }, []);

  // Reseta o form toda vez que o modal abre, aplicando os pré-preenchimentos
  // vindos do clique no card/data. Sem isso, valores da reserva anterior
  // persistem entre aberturas (o componente fica montado no pai).
  useEffect(() => {
    if (!open) return;
    const loc = preLocalId ? locais.find((l) => l.id === preLocalId) : null;
    setForm({
      titulo: "",
      motivo: "",
      observacoes: "",
      campi_id: preCampiId || loc?.campi_id || "",
      grupo_id: preGrupoId || loc?.grupo_id || "",
      local_id: preLocalId || "",
      data_inicial: preData || "",
      data_final: preData || "",
      horario_inicial: "",
      horario_final: "",
    });
    setTipoReserva("unica");
    setDiasRecorrente({});
    setRecursosSelecionados({});
    setErroEnvio(null);
    setLoading(false);
  }, [open, preCampiId, preGrupoId, preLocalId, preData, locais]);

  const gruposFiltrados = grupos.filter((g) => g.campi_id === form.campi_id);
  const locaisFiltrados = locais.filter((l) => l.grupo_id === form.grupo_id);

  const localSelecionado = locais.find((l) => l.id === form.local_id);
  const tipoLocal = localSelecionado?.tipo || "";

  // Generate occurrences for recurring reservation
  const gerarOcorrencias = useMemo(() => () => {
    if (!form.data_inicial || !form.data_final) return [];
    const start = parseISO(form.data_inicial);
    const end = parseISO(form.data_final);
    if (end < start) return [];
    const occ = [];
    eachDayOfInterval({ start, end }).forEach((d) => {
      const wd = getDay(d);
      const cfg = diasRecorrente[wd];
      if (cfg && cfg.horario_inicial && cfg.horario_final) {
        occ.push({
          date: fnsFormat(d, "yyyy-MM-dd"),
          horario_inicial: cfg.horario_inicial,
          horario_final: cfg.horario_final,
        });
      }
    });
    return occ;
  }, [form.data_inicial, form.data_final, diasRecorrente]);

  // Consulta recursos com saldo real na janela escolhida. Reroda sempre que
  // datas/horários/dias mudam. Se faltar informação para calcular, esvazia
  // a lista (a seção "Recursos adicionais" some).
  useEffect(() => {
    let payload = null;
    if (tipoReserva === "unica") {
      if (form.data_inicial && form.data_final && form.horario_inicial && form.horario_final && form.horario_inicial < form.horario_final) {
        payload = { ocorrencias: [{
          data_inicial: form.data_inicial,
          data_final: form.data_final,
          horario_inicial: form.horario_inicial,
          horario_final: form.horario_final,
        }] };
      }
    } else {
      const occ = gerarOcorrencias();
      if (occ.length > 0) {
        payload = { ocorrencias: occ.map((o) => ({
          data_inicial: o.date, data_final: o.date,
          horario_inicial: o.horario_inicial, horario_final: o.horario_final,
        })) };
      }
    }

    if (!payload) {
      setRecursosDisponiveis([]);
      return;
    }
    let cancel = false;
    base44.entities.Recurso.disponiveis(payload)
      .then((list) => { if (!cancel) setRecursosDisponiveis(list || []); })
      .catch(() => { if (!cancel) setRecursosDisponiveis([]); });
    return () => { cancel = true; };
  }, [tipoReserva, form.data_inicial, form.data_final, form.horario_inicial, form.horario_final, gerarOcorrencias]);

  // Se um recurso selecionado saiu da lista disponível, remove-o da seleção.
  useEffect(() => {
    setRecursosSelecionados((prev) => {
      const ids = new Set(recursosDisponiveis.map((r) => String(r.id)));
      const next = {};
      Object.entries(prev).forEach(([id, q]) => {
        if (ids.has(String(id))) next[id] = q;
      });
      return next;
    });
  }, [recursosDisponiveis]);

  // Single reservation conflict check
  const conflito = useMemo(() => {
    if (tipoReserva !== "unica") return null;
    if (!form.local_id || !form.data_inicial || !form.data_final || !form.horario_inicial || !form.horario_final) return null;
    const ini = parseISO(form.data_inicial);
    const fim = parseISO(form.data_final);
    return reservasExistentes.find((r) => {
      if (r.local_id !== form.local_id) return false;
      const rIni = parseISO(r.data_inicial);
      const rFim = parseISO(r.data_final);
      if (!(ini <= rFim && fim >= rIni)) return false;
      const [hi, mi] = form.horario_inicial.split(":").map(Number);
      const [hf, mf] = form.horario_final.split(":").map(Number);
      const [rhi, rmi] = r.horario_inicial.split(":").map(Number);
      const [rhf, rmf] = r.horario_final.split(":").map(Number);
      const s1 = hi * 60 + mi, e1 = hf * 60 + mf;
      const s2 = rhi * 60 + rmi, e2 = rhf * 60 + rmf;
      return s1 < e2 && s2 < e1;
    });
  }, [tipoReserva, form.local_id, form.data_inicial, form.data_final, form.horario_inicial, form.horario_final, reservasExistentes]);

  // Recurring conflicts
  const conflitosRecorrente = useMemo(() => {
    if (tipoReserva !== "recorrente" || !form.local_id) return [];
    const occ = gerarOcorrencias();
    const conflicts = [];
    occ.forEach((o) => {
      const c = reservasExistentes.find((r) => {
        if (r.local_id !== form.local_id) return false;
        if (o.date < r.data_inicial || o.date > r.data_final) return false;
        const [hi, mi] = o.horario_inicial.split(":").map(Number);
        const [hf, mf] = o.horario_final.split(":").map(Number);
        const [rhi, rmi] = r.horario_inicial.split(":").map(Number);
        const [rhf, rmf] = r.horario_final.split(":").map(Number);
        const s1 = hi * 60 + mi, e1 = hf * 60 + mf;
        const s2 = rhi * 60 + rmi, e2 = rhf * 60 + rmf;
        return s1 < e2 && s2 < e1;
      });
      if (c) conflicts.push({ date: o.date, titulo: c.titulo, horario: `${c.horario_inicial} - ${c.horario_final}` });
    });
    return conflicts;
  }, [tipoReserva, form.local_id, gerarOcorrencias, reservasExistentes]);

  // Validations
  const palavrasMotivo = form.motivo.trim().split(/\s+/).filter(Boolean).length;
  const motivoValido = palavrasMotivo >= 10;
  const datasValidas = form.data_inicial && form.data_final && parseISO(form.data_final) >= parseISO(form.data_inicial);
  const horariosValidos = form.horario_inicial && form.horario_final && form.horario_inicial < form.horario_final;

  const diasSelecionados = Object.entries(diasRecorrente).filter(([, v]) => v && v.horario_inicial && v.horario_final);
  const diasHorariosValidos = diasSelecionados.length > 0 && diasSelecionados.every(([, v]) => v.horario_inicial < v.horario_final);
  const ocorrencias = tipoReserva === "recorrente" ? gerarOcorrencias() : [];

  const recorrenteValido = datasValidas && diasHorariosValidos && conflitosRecorrente.length === 0 && ocorrencias.length > 0;
  const unicaValida = datasValidas && horariosValidos && !conflito;

  const podeSalvar = form.titulo && form.campi_id && form.grupo_id && form.local_id && motivoValido &&
    (tipoReserva === "unica" ? unicaValida : recorrenteValido);

  const handleSubmit = async () => {
    if (!podeSalvar) return;
    setLoading(true);
    setErroEnvio(null);
    try {
      const base = {
        titulo: form.titulo,
        motivo: form.motivo,
        observacoes: form.observacoes,
        campi_id: form.campi_id,
        grupo_id: form.grupo_id,
        local_id: form.local_id,
        tipo_local: tipoLocal,
        responsavel_nome: user?.full_name || user?.email || "Usuário",
        status: "confirmada",
      };
      const recursosPayload = Object.entries(recursosSelecionados)
        .filter(([, q]) => q && q > 0)
        .map(([id, q]) => ({ id: parseInt(id, 10), quantidade: parseInt(q, 10) }));
      if (tipoReserva === "unica") {
        await base44.entities.Reserva.create({
          ...base,
          data_inicial: form.data_inicial,
          data_final: form.data_final,
          horario_inicial: form.horario_inicial,
          horario_final: form.horario_final,
          recorrente: false,
          recursos: recursosPayload,
        });
      } else {
        const payloads = ocorrencias.map((o) => ({
          ...base,
          data_inicial: o.date,
          data_final: o.date,
          horario_inicial: o.horario_inicial,
          horario_final: o.horario_final,
          recorrente: true,
          recursos: recursosPayload,
        }));
        await base44.entities.Reserva.bulkCreate(payloads);
      }
      onCreated?.();
      onClose?.();
    } catch (e) {
      console.error(e);
      const data = e?.data;
      let msg = e?.message || "Erro ao salvar a reserva.";
      if (data?.errors && typeof data.errors === "object") {
        const first = Object.values(data.errors).flat()[0];
        if (first) msg = first;
      } else if (data?.message) {
        msg = data.message;
      }
      if (e?.status === 401) msg = "Sua sessão expirou. Faça login novamente.";
      setErroEnvio(msg);
    } finally {
      setLoading(false);
    }
  };

  const LockTag = () => (
    <span className="ml-auto inline-flex items-center gap-1 text-[10px] font-medium text-slate-400">
      <Lock className="h-3 w-3" /> fixo
    </span>
  );

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <span className="h-3 w-3 rounded-full" style={{ backgroundColor: tipoLocal ? getCorTipo(tipoLocal) : "#94a3b8" }} />
            Nova Reserva
          </DialogTitle>
        </DialogHeader>

        <div className="grid gap-4 py-2">
          <div>
            <Label className="mb-1.5">Título da reserva *</Label>
            <Input value={form.titulo} onChange={(e) => setForm({ ...form, titulo: e.target.value })} placeholder="Ex: Aula de Cálculo I" />
          </div>

          <div className="grid gap-4 sm:grid-cols-3">
            <div>
              <Label className="mb-1.5 flex items-center">Campi * {locked && <LockTag />}</Label>
              <Select value={form.campi_id} onValueChange={(v) => setForm({ ...form, campi_id: v, grupo_id: "", local_id: "" })} disabled={locked}>
                <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                <SelectContent>
                  {campi.map((c) => <SelectItem key={c.id} value={c.id}>{c.nome}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="mb-1.5 flex items-center">Grupo * {locked && <LockTag />}</Label>
              <Select value={form.grupo_id} onValueChange={(v) => setForm({ ...form, grupo_id: v, local_id: "" })} disabled={locked || !form.campi_id}>
                <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                <SelectContent>
                  {gruposFiltrados.map((g) => <SelectItem key={g.id} value={g.id}>{g.nome}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="mb-1.5 flex items-center">Local * {locked && <LockTag />}</Label>
              <Select value={form.local_id} onValueChange={(v) => setForm({ ...form, local_id: v })} disabled={locked || !form.grupo_id}>
                <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                <SelectContent>
                  {locaisFiltrados.map((l) => (
                    <SelectItem key={l.id} value={l.id}>
                      <span className="flex items-center gap-2">
                        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: getCorTipo(l.tipo) }} />
                        {l.nome}
                      </span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          {localSelecionado && (
            <div className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
              <span className="h-3 w-3 rounded-full" style={{ backgroundColor: getCorTipo(localSelecionado.tipo) }} />
              <span className="font-medium text-slate-700">Tipo: {localSelecionado.tipo}</span>
              <span className="text-slate-400">·</span>
              <span className="text-slate-600">Capacidade: {localSelecionado.capacidade} pessoas</span>
            </div>
          )}

          {/* Tipo de reserva toggle */}
          <div>
            <Label className="mb-1.5">Tipo de reserva *</Label>
            <div className="flex items-center gap-1 rounded-lg bg-slate-100 p-1 w-full sm:w-fit">
              <button
                type="button"
                onClick={() => setTipoReserva("unica")}
                className={`flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors sm:flex-none ${tipoReserva === "unica" ? "bg-white text-blue-700 shadow-sm" : "text-slate-500 hover:text-slate-700"}`}
              >
                Reserva única
              </button>
              <button
                type="button"
                onClick={() => setTipoReserva("recorrente")}
                className={`flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors sm:flex-none ${tipoReserva === "recorrente" ? "bg-white text-blue-700 shadow-sm" : "text-slate-500 hover:text-slate-700"}`}
              >
                Reserva recorrente
              </button>
            </div>
          </div>

          {periodos.length > 0 && (
            <div>
              <Label className="mb-1.5">Semestres</Label>
              <div className="flex flex-wrap gap-1.5">
                {periodos.map((p) => (
                  <button
                    key={p.id}
                    type="button"
                    onClick={() => {
                      setForm({ ...form, data_inicial: p.data_inicio, data_final: p.data_fim });
                      setTipoReserva("recorrente");
                    }}
                    className="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:border-blue-300 hover:bg-blue-50"
                    title={`${p.data_inicio} → ${p.data_fim}`}
                  >
                    {p.nome}
                  </button>
                ))}
              </div>
              <p className="mt-1 text-[11px] text-slate-400">Clique em um semestre para preencher as datas e ativar a reserva recorrente. Depois escolha os dias da semana e horários.</p>
            </div>
          )}

          {tipoReserva === "unica" ? (
            <>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label className="mb-1.5">Data inicial *</Label>
                  <Input type="date" value={form.data_inicial} onChange={(e) => setForm({ ...form, data_inicial: e.target.value })} />
                </div>
                <div>
                  <Label className="mb-1.5">Data final *</Label>
                  <Input type="date" value={form.data_final} min={form.data_inicial} onChange={(e) => setForm({ ...form, data_final: e.target.value })} />
                </div>
                <div>
                  <Label className="mb-1.5">Horário inicial *</Label>
                  <TimeInput value={form.horario_inicial} onChange={(e) => setForm({ ...form, horario_inicial: e.target.value })} />
                </div>
                <div>
                  <Label className="mb-1.5">Horário final *</Label>
                  <TimeInput value={form.horario_final} onChange={(e) => setForm({ ...form, horario_final: e.target.value })} />
                </div>
              </div>
              {form.data_inicial && form.data_final && !datasValidas && (
                <p className="flex items-center gap-1.5 text-sm text-red-600"><AlertTriangle className="h-4 w-4" /> A data final não pode ser anterior à data inicial.</p>
              )}
              {form.horario_inicial && form.horario_final && !horariosValidos && (
                <p className="flex items-center gap-1.5 text-sm text-red-600"><AlertTriangle className="h-4 w-4" /> O horário final deve ser posterior ao inicial.</p>
              )}
            </>
          ) : (
            <>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label className="mb-1.5">Início do período *</Label>
                  <Input type="date" value={form.data_inicial} onChange={(e) => setForm({ ...form, data_inicial: e.target.value })} />
                </div>
                <div>
                  <Label className="mb-1.5">Fim do período *</Label>
                  <Input type="date" value={form.data_final} min={form.data_inicial} onChange={(e) => setForm({ ...form, data_final: e.target.value })} />
                </div>
              </div>
              {form.data_inicial && form.data_final && !datasValidas && (
                <p className="flex items-center gap-1.5 text-sm text-red-600"><AlertTriangle className="h-4 w-4" /> A data final não pode ser anterior à data inicial.</p>
              )}
              <RecurringDaysPicker value={diasRecorrente} onChange={setDiasRecorrente} />
              {ocorrencias.length > 0 && (
                <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                  <span className="font-medium">Serão criadas {ocorrencias.length} reserva(s)</span>
                  {ocorrencias.slice(0, 3).map((o) => (
                    <span key={o.date} className="ml-2 inline-block">{fnsFormat(parseISO(o.date), "dd/MM", { locale: ptBR })} {o.horario_inicial}</span>
                  ))}
                  {ocorrencias.length > 3 && <span className="ml-2 text-slate-400">...</span>}
                </div>
              )}
            </>
          )}

          <div>
            <Label className="mb-1.5">Motivo da reserva * <span className="text-xs font-normal text-slate-400">(mínimo 10 palavras)</span></Label>
            <Textarea value={form.motivo} onChange={(e) => setForm({ ...form, motivo: e.target.value })} placeholder="Ex: Aula da disciplina de Cálculo Diferencial e Integral I para a turma do primeiro semestre" rows={3} />
            {form.motivo && !motivoValido && <p className="mt-1 text-xs text-amber-600">{palavrasMotivo}/10 palavras mínimas</p>}
          </div>

          <div>
            <Label className="mb-1.5">Observações</Label>
            <Textarea value={form.observacoes} onChange={(e) => setForm({ ...form, observacoes: e.target.value })} placeholder="Informações adicionais sobre a reserva (opcional)" rows={2} />
          </div>

          {recursosDisponiveis.length > 0 && (
            <div>
              <Label className="mb-1.5">Recursos adicionais</Label>
              <p className="mb-2 text-xs text-slate-400">Selecione os recursos e a quantidade. Os saldos abaixo já consideram a janela e as reservas concorrentes.</p>
              <div className="space-y-1 rounded-lg border border-slate-200 p-2">
                {recursosDisponiveis.map((rec) => {
                  const saldo = rec.saldo_minimo || 0;
                  const qtd = recursosSelecionados[rec.id] || 0;
                  return (
                    <div key={rec.id} className="flex items-center gap-2 rounded px-1 py-1 hover:bg-slate-50">
                      <input
                        type="checkbox"
                        checked={qtd > 0}
                        onChange={(e) => setRecursosSelecionados((prev) => {
                          const next = { ...prev };
                          if (e.target.checked) next[rec.id] = 1;
                          else delete next[rec.id];
                          return next;
                        })}
                      />
                      <span className="flex-1 text-sm text-slate-700">{rec.nome}</span>
                      <span className="text-xs text-slate-400">saldo {saldo}</span>
                      <Input
                        type="number"
                        min={1}
                        max={saldo}
                        value={qtd || ""}
                        disabled={qtd === 0}
                        onChange={(e) => setRecursosSelecionados((prev) => ({ ...prev, [rec.id]: Math.max(1, Math.min(saldo, parseInt(e.target.value, 10) || 1)) }))}
                        className="h-8 w-16 text-xs"
                      />
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Conflict message */}
          {tipoReserva === "unica" && conflito && (
            <div className="rounded-lg border border-red-200 bg-red-50 p-3">
              <div className="flex items-start gap-2">
                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <div>
                  <p className="text-sm font-semibold text-red-800">Não foi possível concluir a reserva.</p>
                  <p className="text-sm text-red-700">O local já possui reserva nesse período: <strong>{conflito.titulo}</strong> ({conflito.horario_inicial} - {conflito.horario_final}).</p>
                </div>
              </div>
            </div>
          )}
          {tipoReserva === "recorrente" && conflitosRecorrente.length > 0 && (
            <div className="rounded-lg border border-red-200 bg-red-50 p-3">
              <div className="flex items-start gap-2">
                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <div className="w-full">
                  <p className="text-sm font-semibold text-red-800">Conflito(s) detectado(s) em {conflitosRecorrente.length} data(s):</p>
                  <ul className="mt-1 max-h-32 space-y-0.5 overflow-y-auto text-sm text-red-700">
                    {conflitosRecorrente.map((c, i) => (
                      <li key={i}>{fnsFormat(parseISO(c.date), "dd/MM/yyyy", { locale: ptBR })} · conflita com <strong>{c.titulo}</strong> ({c.horario})</li>
                    ))}
                  </ul>
                </div>
              </div>
            </div>
          )}

          {erroEnvio && (
            <div className="rounded-lg border border-red-200 bg-red-50 p-3">
              <div className="flex items-start gap-2">
                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <div>
                  <p className="text-sm font-semibold text-red-800">Não foi possível salvar a reserva.</p>
                  <p className="text-sm text-red-700">{erroEnvio}</p>
                </div>
              </div>
            </div>
          )}

          {/* Success preview */}
          {tipoReserva === "unica" && !conflito && form.local_id && form.data_inicial && form.horario_inicial && (
            <div className="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
              <CheckCircle2 className="h-4 w-4" /> Horário disponível para reserva.
            </div>
          )}
          {tipoReserva === "recorrente" && recorrenteValido && (
            <div className="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
              <CheckCircle2 className="h-4 w-4" /> Todas as {ocorrencias.length} ocorrências estão disponíveis.
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
          <Button onClick={handleSubmit} disabled={!podeSalvar || loading} className="bg-blue-600 hover:bg-blue-700">
            {loading ? "Salvando..." : tipoReserva === "unica" ? "Confirmar Reserva" : `Confirmar ${ocorrencias.length || ""} Reserva(s)`}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}