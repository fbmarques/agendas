import { useEffect, useState, useMemo } from "react";
import { base44 } from "@/api/base44Client";
import { format, parseISO, isToday, isWithinInterval, startOfWeek, endOfWeek } from "date-fns";
import { ptBR } from "date-fns/locale";
import Header from "@/components/Header";
import StatCard from "@/components/StatCard";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Switch } from "@/components/ui/switch";
import { Checkbox } from "@/components/ui/checkbox";
import { Building2, Layers, MapPin, CalendarCheck, TrendingUp, Clock, LayoutDashboard, Users, BarChart3, Settings, Plus, Pencil, Trash2, Search, Tag, ClipboardList, CheckCircle2, XCircle } from "lucide-react";
import { TIPOS_LOCAL, getCorTipo } from "@/lib/tiposLocal";

const SECTIONS = [
  { key: "dashboard", label: "Dashboard", icon: LayoutDashboard },
  { key: "campi", label: "Campi", icon: Building2 },
  { key: "grupos", label: "Grupos", icon: Layers },
  { key: "locais", label: "Locais", icon: MapPin },
  { key: "tipos", label: "Tipos de Local", icon: Tag },
  { key: "reservas", label: "Reservas", icon: CalendarCheck },
  { key: "pendentes", label: "Pendentes", icon: ClipboardList },
  { key: "usuarios", label: "Usuários", icon: Users },
  { key: "relatorios", label: "Relatórios", icon: BarChart3 },
  { key: "config", label: "Configurações", icon: Settings },
];

export default function Admin() {
  const [section, setSection] = useState("dashboard");
  const [campi, setCampi] = useState([]);
  const [grupos, setGrupos] = useState([]);
  const [locais, setLocais] = useState([]);
  const [reservas, setReservas] = useState([]);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editItem, setEditItem] = useState(null);
  const [search, setSearch] = useState("");

  const loadAll = () => {
    Promise.all([
      base44.entities.Campi.list(),
      base44.entities.Grupo.list(),
      base44.entities.Local.list(),
      base44.entities.Reserva.list(),
      base44.entities.User.list().catch(() => []),
    ]).then(([c, g, l, r, u]) => {
      setCampi(c || []);
      setGrupos(g || []);
      setLocais(l || []);
      setReservas(r || []);
      setUsers(u || []);
      setLoading(false);
    });
  };

  useEffect(() => { loadAll(); }, []);

  const sWeek = startOfWeek(new Date(), { weekStartsOn: 0 });
  const eWeek = endOfWeek(new Date(), { weekStartsOn: 0 });
  const reservasHoje = reservas.filter((r) => {
    const ini = parseISO(r.data_inicial), fim = parseISO(r.data_final);
    return isToday(ini) || isToday(fim) || (ini <= new Date() && fim >= new Date());
  });
  const reservasSemana = reservas.filter((r) => {
    const ini = parseISO(r.data_inicial);
    return isWithinInterval(ini, { start: sWeek, end: eWeek });
  });

  const locaisMaisReservados = useMemo(() => {
    const counts = {};
    reservas.forEach((r) => { counts[r.local_id] = (counts[r.local_id] || 0) + 1; });
    return Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 5).map(([id, count]) => {
      const l = locais.find((x) => x.id === id);
      return { nome: l?.nome || "—", count, tipo: l?.tipo };
    });
  }, [reservas, locais]);

  const taxaOcupacao = locais.length ? Math.round((reservasHoje.length / locais.length) * 100) : 0;

  const proximasReservas = useMemo(() => {
    const now = new Date();
    return reservas.filter((r) => parseISO(r.data_inicial) >= now).sort((a, b) => parseISO(a.data_inicial) - parseISO(b.data_inicial)).slice(0, 5);
  }, [reservas]);

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-50">
        <Header />
        <div className="flex h-[60vh] items-center justify-center">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600" />
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <Header />
      <div className="mx-auto flex max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:px-8">
        {/* Sidebar */}
        <aside className="hidden w-60 shrink-0 lg:block">
          <nav className="sticky top-20 space-y-1 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
            {SECTIONS.map((s) => (
              <button
                key={s.key}
                onClick={() => setSection(s.key)}
                className={`flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${section === s.key ? "bg-blue-50 text-blue-700" : "text-slate-600 hover:bg-slate-100"}`}
              >
                <s.icon className="h-4 w-4" /> {s.label}
              </button>
            ))}
          </nav>
        </aside>

        {/* Mobile section selector */}
        <div className="lg:hidden">
          <Select value={section} onValueChange={setSection}>
            <SelectTrigger className="w-48"><SelectValue /></SelectTrigger>
            <SelectContent>
              {SECTIONS.map((s) => <SelectItem key={s.key} value={s.key}>{s.label}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>

        {/* Content */}
        <main className="flex-1">
          {section === "dashboard" && (
            <div>
              <h1 className="mb-1 text-2xl font-bold text-slate-900">Dashboard</h1>
              <p className="mb-6 text-sm text-slate-500">Visão geral do sistema de reservas.</p>
              <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard icon={Building2} label="Campi" value={campi.length} color="blue" />
                <StatCard icon={Layers} label="Grupos" value={grupos.length} color="purple" />
                <StatCard icon={MapPin} label="Locais" value={locais.length} color="green" />
                <StatCard icon={CalendarCheck} label="Reservas hoje" value={reservasHoje.length} color="orange" />
              </div>
              <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <StatCard icon={CalendarCheck} label="Reservas da semana" value={reservasSemana.length} color="teal" />
                <StatCard icon={TrendingUp} label="Taxa de ocupação" value={`${taxaOcupacao}%`} color="indigo" subtitle="Baseado em hoje" />
                <StatCard icon={Users} label="Usuários" value={users.length} color="red" />
              </div>

              <div className="mt-6 grid gap-4 lg:grid-cols-2">
                {/* Locais mais reservados */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                  <h3 className="mb-4 flex items-center gap-2 text-sm font-bold text-slate-800"><BarChart3 className="h-4 w-4" /> Locais mais reservados</h3>
                  <div className="space-y-3">
                    {locaisMaisReservados.length === 0 ? <p className="text-sm text-slate-400">Sem dados.</p> : locaisMaisReservados.map((l, i) => (
                      <div key={i} className="flex items-center gap-3">
                        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: getCorTipo(l.tipo) }} />
                        <span className="flex-1 text-sm text-slate-700">{l.nome}</span>
                        <div className="h-2 w-24 overflow-hidden rounded-full bg-slate-100">
                          <div className="h-full rounded-full bg-blue-500" style={{ width: `${(l.count / locaisMaisReservados[0].count) * 100}%` }} />
                        </div>
                        <span className="w-6 text-right text-xs font-semibold text-slate-600">{l.count}</span>
                      </div>
                    ))}
                  </div>
                </div>
                {/* Próximas reservas */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                  <h3 className="mb-4 flex items-center gap-2 text-sm font-bold text-slate-800"><Clock className="h-4 w-4" /> Próximas reservas</h3>
                  <div className="space-y-2">
                    {proximasReservas.length === 0 ? <p className="text-sm text-slate-400">Sem reservas futuras.</p> : proximasReservas.map((r) => (
                      <div key={r.id} className="flex items-center gap-3 border-b border-slate-50 pb-2">
                        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: getCorTipo(r.tipo_local) }} />
                        <div className="flex-1">
                          <p className="text-sm font-medium text-slate-700">{r.titulo}</p>
                          <p className="text-xs text-slate-400">{format(parseISO(r.data_inicial), "dd/MM", { locale: ptBR })} · {r.horario_inicial}</p>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          )}

          {section === "campi" && (
            <AdminTable
              title="Campi" items={campi} search={search} setSearch={setSearch}
              columns={["nome", "sigla", "cidade", "status"]}
              onAdd={() => { setEditItem(null); setModalOpen(true); }}
              onEdit={(item) => { setEditItem(item); setModalOpen(true); }}
              onDelete={(item) => base44.entities.Campi.delete(item.id).then(loadAll)}
              renderForm={() => <CampiForm item={editItem} campi={campi} grupos={grupos} locais={locais} onSaved={() => { setModalOpen(false); loadAll(); }} />}
              modalOpen={modalOpen} setModalOpen={setModalOpen}
            />
          )}
          {section === "grupos" && (
            <AdminTable
              title="Grupos" items={grupos} search={search} setSearch={setSearch}
              columns={["nome", "status"]}
              extraCol={{ header: "Campi", render: (g) => campi.find((c) => c.id === g.campi_id)?.nome }}
              onAdd={() => { setEditItem(null); setModalOpen(true); }}
              onEdit={(item) => { setEditItem(item); setModalOpen(true); }}
              onDelete={(item) => base44.entities.Grupo.delete(item.id).then(loadAll)}
              renderForm={() => <GrupoForm item={editItem} campi={campi} onSaved={() => { setModalOpen(false); loadAll(); }} />}
              modalOpen={modalOpen} setModalOpen={setModalOpen}
            />
          )}
          {section === "locais" && (
            <AdminTable
              title="Locais" items={locais} search={search} setSearch={setSearch}
              columns={["nome", "tipo", "capacidade", "status"]}
              extraCol={{ header: "Grupo", render: (l) => grupos.find((g) => g.id === l.grupo_id)?.nome }}
              colorCol="tipo"
              onAdd={() => { setEditItem(null); setModalOpen(true); }}
              onEdit={(item) => { setEditItem(item); setModalOpen(true); }}
              onDelete={(item) => base44.entities.Local.delete(item.id).then(loadAll)}
              renderForm={() => <LocalForm item={editItem} campi={campi} grupos={grupos} users={users} onSaved={() => { setModalOpen(false); loadAll(); }} />}
              modalOpen={modalOpen} setModalOpen={setModalOpen}
            />
          )}
          {section === "pendentes" && (
            <PendentesSection users={users} locais={locais} onChanged={loadAll} />
          )}
          {section === "tipos" && (
            <div>
              <h1 className="mb-1 text-2xl font-bold text-slate-900">Tipos de Local</h1>
              <p className="mb-6 text-sm text-slate-500">Cores associadas a cada tipo para identificação visual.</p>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {TIPOS_LOCAL.map((t) => (
                  <div key={t.nome} className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <span className="h-8 w-8 rounded-lg" style={{ backgroundColor: t.cor }} />
                    <div>
                      <p className="text-sm font-semibold text-slate-800">{t.nome}</p>
                      <p className="text-xs text-slate-400">{t.cor}</p>
                    </div>
                    <span className="ml-auto rounded-full px-2 py-0.5 text-[10px] font-semibold" style={{ backgroundColor: t.cor + "22", color: t.cor }}>Cor única</span>
                  </div>
                ))}
              </div>
            </div>
          )}
          {section === "reservas" && (
            <AdminTable
              title="Reservas" items={reservas} search={search} setSearch={setSearch}
              columns={["titulo", "tipo_local", "status"]}
              extraCol={{ header: "Local", render: (r) => locais.find((l) => l.id === r.local_id)?.nome }}
              extraCol2={{ header: "Data", render: (r) => format(parseISO(r.data_inicial), "dd/MM/yyyy") }}
              colorCol="tipo_local"
              onAdd={() => { setEditItem(null); setModalOpen(true); }}
              onEdit={(item) => { setEditItem(item); setModalOpen(true); }}
              onDelete={(item) => base44.entities.Reserva.delete(item.id).then(loadAll)}
              renderForm={() => <div className="p-4 text-sm text-slate-500">Use a página do campi para criar reservas com validação de conflito.</div>}
              modalOpen={modalOpen} setModalOpen={setModalOpen}
            />
          )}
          {section === "usuarios" && (
            <AdminTable
              title="Usuários" items={users} search={search} setSearch={setSearch}
              columns={["full_name", "email", "role"]}
              noActions
            />
          )}
          {section === "relatorios" && (
            <div>
              <h1 className="mb-1 text-2xl font-bold text-slate-900">Relatórios</h1>
              <p className="mb-6 text-sm text-slate-500">Métricas e estatísticas de uso.</p>
              <div className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                  <h3 className="mb-4 text-sm font-bold text-slate-800">Reservas por tipo de local</h3>
                  {TIPOS_LOCAL.map((t) => {
                    const count = reservas.filter((r) => r.tipo_local === t.nome).length;
                    const max = Math.max(1, ...TIPOS_LOCAL.map((x) => reservas.filter((r) => r.tipo_local === x.nome).length));
                    return (
                      <div key={t.nome} className="mb-2 flex items-center gap-3">
                        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: t.cor }} />
                        <span className="w-40 text-xs text-slate-600">{t.nome}</span>
                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full" style={{ width: `${(count / max) * 100}%`, backgroundColor: t.cor }} /></div>
                        <span className="w-6 text-right text-xs font-semibold text-slate-600">{count}</span>
                      </div>
                    );
                  })}
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                  <h3 className="mb-4 text-sm font-bold text-slate-800">Reservas por campi</h3>
                  {campi.map((c) => {
                    const count = reservas.filter((r) => r.campi_id === c.id).length;
                    const max = Math.max(1, ...campi.map((x) => reservas.filter((r) => r.campi_id === x.id).length));
                    return (
                      <div key={c.id} className="mb-2 flex items-center gap-3">
                        <span className="w-32 text-xs text-slate-600">{c.nome}</span>
                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-blue-500" style={{ width: `${(count / max) * 100}%` }} /></div>
                        <span className="w-6 text-right text-xs font-semibold text-slate-600">{count}</span>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          )}
          {section === "config" && (
            <div>
              <h1 className="mb-1 text-2xl font-bold text-slate-900">Configurações</h1>
              <p className="mb-6 text-sm text-slate-500">Configurações gerais do sistema.</p>
              <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p className="text-sm text-slate-500">As configurações do sistema serão gerenciadas nesta seção. Em um protótipo, estas opções são ilustrativas.</p>
              </div>
            </div>
          )}
        </main>
      </div>
    </div>
  );
}

function AdminTable({ title, items, search, setSearch, columns, extraCol, extraCol2, colorCol, onAdd, onEdit, onDelete, renderForm, modalOpen, setModalOpen, noActions }) {
  const filtered = items.filter((item) =>
    columns.some((c) => String(item[c] || "").toLowerCase().includes(search.toLowerCase()))
  );
  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-900">{title}</h1>
        {!noActions && <Button className="bg-blue-600 hover:bg-blue-700" onClick={onAdd}><Plus className="h-4 w-4" /> Adicionar</Button>}
      </div>
      <div className="relative mb-4">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar..." className="pl-9 max-w-xs" />
      </div>
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-xs uppercase text-slate-400">
              <tr>
                {columns.map((c) => <th key={c} className="px-4 py-3 text-left font-semibold">{c}</th>)}
                {extraCol && <th className="px-4 py-3 text-left font-semibold">{extraCol.header}</th>}
                {extraCol2 && <th className="px-4 py-3 text-left font-semibold">{extraCol2.header}</th>}
                {!noActions && <th className="px-4 py-3 text-right font-semibold">Ações</th>}
              </tr>
            </thead>
            <tbody>
              {filtered.length === 0 ? (
                <tr><td colSpan={columns.length + 3} className="px-4 py-8 text-center text-slate-400">Nenhum registro encontrado.</td></tr>
              ) : filtered.map((item) => (
                <tr key={item.id} className="border-t border-slate-100 hover:bg-slate-50">
                  {columns.map((c) => (
                    <td key={c} className="px-4 py-3 text-slate-700">
                      {colorCol === c && <span className="mr-2 inline-block h-2.5 w-2.5 rounded-full align-middle" style={{ backgroundColor: getCorTipo(item[c]) }} />}
                      {c === "status" ? <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${item[c] === "ativo" || item[c] === "confirmada" ? "bg-green-100 text-green-700" : item[c] === "pendente" ? "bg-amber-100 text-amber-700" : "bg-red-100 text-red-700"}`}>{item[c]}</span> : String(item[c] || "")}
                    </td>
                  ))}
                  {extraCol && <td className="px-4 py-3 text-slate-600">{extraCol.render(item)}</td>}
                  {extraCol2 && <td className="px-4 py-3 text-slate-600">{extraCol2.render(item)}</td>}
                  {!noActions && (
                    <td className="px-4 py-3 text-right">
                      <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="icon" onClick={() => onEdit(item)}><Pencil className="h-4 w-4 text-slate-400" /></Button>
                        <Button variant="ghost" size="icon" onClick={() => onDelete(item)}><Trash2 className="h-4 w-4 text-red-400" /></Button>
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {modalOpen && (
        <Dialog open={modalOpen} onOpenChange={setModalOpen}>
          <DialogContent className="max-w-lg">
            <DialogHeader><DialogTitle>{renderForm ? "Formulário" : ""}</DialogTitle></DialogHeader>
            {renderForm()}
          </DialogContent>
        </Dialog>
      )}
    </div>
  );
}

function CampiForm({ item, onSaved }) {
  const [form, setForm] = useState(item || { nome: "", sigla: "", endereco: "", cidade: "", descricao: "", status: "ativo" });
  const save = async () => {
    if (item?.id) await base44.entities.Campi.update(item.id, form);
    else await base44.entities.Campi.create(form);
    onSaved();
  };
  return (
    <div className="space-y-3">
      <div><Label>Nome *</Label><Input value={form.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} /></div>
      <div className="grid grid-cols-2 gap-3">
        <div><Label>Sigla *</Label><Input value={form.sigla} onChange={(e) => setForm({ ...form, sigla: e.target.value })} /></div>
        <div><Label>Cidade</Label><Input value={form.cidade} onChange={(e) => setForm({ ...form, cidade: e.target.value })} /></div>
      </div>
      <div><Label>Endereço</Label><Input value={form.endereco} onChange={(e) => setForm({ ...form, endereco: e.target.value })} /></div>
      <div><Label>Descrição</Label><Textarea value={form.descricao} onChange={(e) => setForm({ ...form, descricao: e.target.value })} rows={2} /></div>
      <div><Label>Status</Label><Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="ativo">Ativo</SelectItem><SelectItem value="inativo">Inativo</SelectItem></SelectContent></Select></div>
      <DialogFooter><Button variant="outline" onClick={onSaved}>Cancelar</Button><Button className="bg-blue-600" onClick={save}>Salvar</Button></DialogFooter>
    </div>
  );
}

function GrupoForm({ item, campi, onSaved }) {
  const [form, setForm] = useState(item || { nome: "", campi_id: "", descricao: "", status: "ativo" });
  const save = async () => {
    if (item?.id) await base44.entities.Grupo.update(item.id, form);
    else await base44.entities.Grupo.create(form);
    onSaved();
  };
  return (
    <div className="space-y-3">
      <div><Label>Nome *</Label><Input value={form.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} /></div>
      <div><Label>Campi *</Label><Select value={form.campi_id} onValueChange={(v) => setForm({ ...form, campi_id: v })}><SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger><SelectContent>{campi.map((c) => <SelectItem key={c.id} value={c.id}>{c.nome}</SelectItem>)}</SelectContent></Select></div>
      <div><Label>Descrição</Label><Textarea value={form.descricao} onChange={(e) => setForm({ ...form, descricao: e.target.value })} rows={2} /></div>
      <div><Label>Status</Label><Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="ativo">Ativo</SelectItem><SelectItem value="inativo">Inativo</SelectItem></SelectContent></Select></div>
      <DialogFooter><Button variant="outline" onClick={onSaved}>Cancelar</Button><Button className="bg-blue-600" onClick={save}>Salvar</Button></DialogFooter>
    </div>
  );
}

function LocalForm({ item, campi, grupos, users, onSaved }) {
  const initial = item || { nome: "", campi_id: "", grupo_id: "", tipo: "Sala de aula", capacidade: 0, descricao: "", recursos: "", status: "ativo", requer_aprovacao: false };
  const [form, setForm] = useState({ ...initial, requer_aprovacao: !!initial.requer_aprovacao });
  const [gerentesIds, setGerentesIds] = useState([]);
  const gruposFiltrados = grupos.filter((g) => g.campi_id === form.campi_id);

  useEffect(() => {
    if (!item?.id) { setGerentesIds([]); return; }
    base44.entities.Local.gerentes(item.id).then((list) => {
      setGerentesIds((list || []).map((u) => String(u.id)));
    }).catch(() => setGerentesIds([]));
  }, [item?.id]);

  const toggleGerente = (id) => {
    const s = String(id);
    setGerentesIds((prev) => prev.includes(s) ? prev.filter((x) => x !== s) : [...prev, s]);
  };

  const save = async () => {
    const payload = { ...form, gerentes: gerentesIds.map((i) => parseInt(i, 10)) };
    if (item?.id) await base44.entities.Local.update(item.id, payload);
    else await base44.entities.Local.create(payload);
    onSaved();
  };

  return (
    <div className="space-y-3">
      <div><Label>Nome *</Label><Input value={form.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} /></div>
      <div className="grid grid-cols-2 gap-3">
        <div><Label>Campi *</Label><Select value={form.campi_id} onValueChange={(v) => setForm({ ...form, campi_id: v, grupo_id: "" })}><SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger><SelectContent>{campi.map((c) => <SelectItem key={c.id} value={c.id}>{c.nome}</SelectItem>)}</SelectContent></Select></div>
        <div><Label>Grupo *</Label><Select value={form.grupo_id} onValueChange={(v) => setForm({ ...form, grupo_id: v })} disabled={!form.campi_id}><SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger><SelectContent>{gruposFiltrados.map((g) => <SelectItem key={g.id} value={g.id}>{g.nome}</SelectItem>)}</SelectContent></Select></div>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>Tipo *</Label>
          <Select value={form.tipo} onValueChange={(v) => setForm({ ...form, tipo: v })}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>{TIPOS_LOCAL.map((t) => <SelectItem key={t.nome} value={t.nome}><span className="flex items-center gap-2"><span className="h-2 w-2 rounded-full" style={{ backgroundColor: t.cor }} />{t.nome}</span></SelectItem>)}</SelectContent>
          </Select>
        </div>
        <div><Label>Capacidade</Label><Input type="number" value={form.capacidade} onChange={(e) => setForm({ ...form, capacidade: parseInt(e.target.value) || 0 })} /></div>
      </div>
      <div><Label>Descrição</Label><Textarea value={form.descricao} onChange={(e) => setForm({ ...form, descricao: e.target.value })} rows={2} /></div>
      <div><Label>Recursos</Label><Input value={form.recursos} onChange={(e) => setForm({ ...form, recursos: e.target.value })} placeholder="Projetor, Ar condicionado..." /></div>
      <div><Label>Status</Label><Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="ativo">Ativo</SelectItem><SelectItem value="inativo">Inativo</SelectItem></SelectContent></Select></div>

      <div className="flex items-center justify-between rounded-lg border border-slate-200 p-3">
        <div>
          <Label className="text-sm font-medium text-slate-800">Requer aprovação</Label>
          <p className="text-xs text-slate-500">Novas reservas ficam pendentes até um gerente aprovar.</p>
        </div>
        <Switch checked={!!form.requer_aprovacao} onCheckedChange={(v) => setForm({ ...form, requer_aprovacao: v })} />
      </div>

      <div>
        <Label>Gerentes do local</Label>
        <p className="mb-2 text-xs text-slate-500">Só gerentes (e admin) podem aprovar ou cancelar reservas deste local.</p>
        <div className="max-h-40 space-y-1 overflow-auto rounded-lg border border-slate-200 p-2">
          {(users || []).length === 0 && <p className="p-2 text-xs text-slate-400">Nenhum usuário cadastrado.</p>}
          {(users || []).map((u) => (
            <label key={u.id} className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-slate-50">
              <Checkbox checked={gerentesIds.includes(String(u.id))} onCheckedChange={() => toggleGerente(u.id)} />
              <span className="text-sm text-slate-700">{u.full_name || u.email}</span>
              <span className="ml-auto text-xs text-slate-400">{u.email}</span>
            </label>
          ))}
        </div>
      </div>

      <DialogFooter><Button variant="outline" onClick={onSaved}>Cancelar</Button><Button className="bg-blue-600" onClick={save}>Salvar</Button></DialogFooter>
    </div>
  );
}

function PendentesSection({ users, locais, onChanged }) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [cancelTarget, setCancelTarget] = useState(null);
  const [motivo, setMotivo] = useState("");

  const load = () => {
    setLoading(true);
    base44.entities.Reserva.pendentes().then((list) => {
      setItems(list || []);
      setLoading(false);
    }).catch(() => { setItems([]); setLoading(false); });
  };
  useEffect(() => { load(); }, []);

  const aprovar = async (id) => {
    await base44.entities.Reserva.aprovar(id);
    load(); onChanged?.();
  };
  const confirmarCancelar = async () => {
    if (!cancelTarget) return;
    const palavras = motivo.trim().split(/\s+/).filter(Boolean).length;
    if (palavras < 5) return;
    try {
      await base44.entities.Reserva.cancelar(cancelTarget.id, motivo);
      setCancelTarget(null); setMotivo("");
      load(); onChanged?.();
    } catch (e) {
      alert(e?.data?.message || "Falha ao cancelar.");
    }
  };

  const nomeUsuario = (id) => users.find((u) => String(u.id) === String(id))?.full_name || "—";
  const nomeLocal = (id) => locais.find((l) => String(l.id) === String(id))?.nome || "—";

  return (
    <div>
      <h1 className="mb-1 text-2xl font-bold text-slate-900">Pendentes</h1>
      <p className="mb-6 text-sm text-slate-500">Reservas aguardando aprovação dos gerentes de cada local.</p>
      {loading ? (
        <p className="text-sm text-slate-400">Carregando...</p>
      ) : items.length === 0 ? (
        <div className="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-400">Nenhuma reserva pendente no momento.</div>
      ) : (
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-xs uppercase text-slate-400">
              <tr>
                <th className="px-4 py-3 text-left font-semibold">Título</th>
                <th className="px-4 py-3 text-left font-semibold">Local</th>
                <th className="px-4 py-3 text-left font-semibold">Data</th>
                <th className="px-4 py-3 text-left font-semibold">Horário</th>
                <th className="px-4 py-3 text-left font-semibold">Solicitante</th>
                <th className="px-4 py-3 text-right font-semibold">Ações</th>
              </tr>
            </thead>
            <tbody>
              {items.map((r) => (
                <tr key={r.id} className="border-t border-slate-100 hover:bg-slate-50">
                  <td className="px-4 py-3 text-slate-700">{r.titulo}</td>
                  <td className="px-4 py-3 text-slate-600">{nomeLocal(r.local_id)}</td>
                  <td className="px-4 py-3 text-slate-600">{format(parseISO(r.data_inicial), "dd/MM/yyyy")}</td>
                  <td className="px-4 py-3 text-slate-600">{String(r.horario_inicial).slice(0, 5)} — {String(r.horario_final).slice(0, 5)}</td>
                  <td className="px-4 py-3 text-slate-600">{nomeUsuario(r.user_id)}</td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex justify-end gap-1">
                      <Button size="sm" className="bg-emerald-600 hover:bg-emerald-700" onClick={() => aprovar(r.id)}>
                        <CheckCircle2 className="h-4 w-4" /> Aprovar
                      </Button>
                      <Button size="sm" variant="outline" className="border-red-200 text-red-600 hover:bg-red-50" onClick={() => { setCancelTarget(r); setMotivo(""); }}>
                        <XCircle className="h-4 w-4" /> Cancelar
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialog open={!!cancelTarget} onOpenChange={(o) => { if (!o) { setCancelTarget(null); setMotivo(""); } }}>
        <DialogContent>
          <DialogHeader><DialogTitle>Cancelar reserva</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <p className="text-sm text-slate-600">Informe o motivo do cancelamento (mínimo 5 palavras). O solicitante será notificado.</p>
            <Textarea rows={4} value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Descreva por que a reserva está sendo cancelada..." />
            <p className="text-xs text-slate-400">
              {motivo.trim().split(/\s+/).filter(Boolean).length} palavra(s)
            </p>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setCancelTarget(null); setMotivo(""); }}>Voltar</Button>
            <Button className="bg-red-600 hover:bg-red-700" onClick={confirmarCancelar}>Confirmar cancelamento</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}