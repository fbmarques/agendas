import { useEffect, useState, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { base44 } from "@/api/base44Client";
import { format, parseISO } from "date-fns";
import { ptBR } from "date-fns/locale";
import Header from "@/components/Header";
import ReservationDetailModal from "@/components/ReservationDetailModal";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { CalendarDays, Eye, Inbox, LogIn } from "lucide-react";
import { getCorTipo } from "@/lib/tiposLocal";

export default function MinhasReservas() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [checking, setChecking] = useState(true);
  const [reservas, setReservas] = useState([]);
  const [campi, setCampi] = useState([]);
  const [locais, setLocais] = useState([]);
  const [grupos, setGrupos] = useState([]);
  const [tab, setTab] = useState("futuras");
  const [filtroCampi, setFiltroCampi] = useState("todos");
  const [filtroStatus, setFiltroStatus] = useState("todos");
  const [detailReserva, setDetailReserva] = useState(null);
  const [detailOpen, setDetailOpen] = useState(false);

  useEffect(() => {
    base44.auth.isAuthenticated().then((ok) => {
      if (!ok) { setChecking(false); return; }
      base44.auth.me().then((u) => {
        setUser(u);
        Promise.all([
          base44.entities.Reserva.list(),
          base44.entities.Campi.list(),
          base44.entities.Local.list(),
          base44.entities.Grupo.list(),
        ]).then(([rs, cs, ls, gs]) => {
          setReservas((rs || []).filter((r) => r.responsavel_nome === (u?.full_name || u?.email)));
          setCampi(cs || []);
          setLocais(ls || []);
          setGrupos(gs || []);
          setChecking(false);
        });
      }).catch(() => setChecking(false));
    });
  }, []);

  const filtradas = useMemo(() => {
    const now = new Date();
    return reservas.filter((r) => {
      if (filtroCampi !== "todos" && r.campi_id !== filtroCampi) return false;
      if (filtroStatus !== "todos" && r.status !== filtroStatus) return false;
      if (tab === "futuras") return parseISO(r.data_inicial) >= now;
      return parseISO(r.data_inicial) < now;
    }).sort((a, b) => {
      const da = parseISO(a.data_inicial), db = parseISO(b.data_inicial);
      return tab === "futuras" ? da - db : db - da;
    });
  }, [reservas, filtroCampi, filtroStatus, tab]);

  if (checking) {
    return (
      <div className="min-h-screen bg-slate-50">
        <Header />
        <div className="flex h-[60vh] items-center justify-center">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600" />
        </div>
      </div>
    );
  }

  if (!user) {
    return (
      <div className="min-h-screen bg-slate-50">
        <Header />
        <div className="flex flex-col items-center justify-center py-20">
          <LogIn className="mb-3 h-12 w-12 text-slate-300" />
          <p className="text-slate-500">Você precisa estar logado para ver suas reservas.</p>
          <Button className="mt-4 bg-blue-600" onClick={() => navigate("/login")}>Fazer login</Button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <Header />
      <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 className="text-2xl font-bold text-slate-900">Minhas Reservas</h1>
        <p className="mt-1 text-sm text-slate-500">Gerencie suas reservas futuras e passadas.</p>

        {/* Tabs */}
        <div className="mt-6 flex items-center gap-1 rounded-lg bg-slate-100 p-1 w-fit">
          {[
            { key: "futuras", label: "Futuras" },
            { key: "passadas", label: "Passadas" },
          ].map((t) => (
            <button key={t.key} onClick={() => setTab(t.key)} className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${tab === t.key ? "bg-white text-blue-700 shadow-sm" : "text-slate-500"}`}>
              {t.label}
            </button>
          ))}
        </div>

        {/* Filters */}
        <div className="mt-4 flex flex-col gap-3 sm:flex-row">
          <Select value={filtroCampi} onValueChange={setFiltroCampi}>
            <SelectTrigger className="sm:w-56"><SelectValue placeholder="Campi" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="todos">Todos os campi</SelectItem>
              {campi.map((c) => <SelectItem key={c.id} value={c.id}>{c.nome}</SelectItem>)}
            </SelectContent>
          </Select>
          <Select value={filtroStatus} onValueChange={setFiltroStatus}>
            <SelectTrigger className="sm:w-48"><SelectValue placeholder="Status" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="todos">Todos os status</SelectItem>
              <SelectItem value="confirmada">Confirmada</SelectItem>
              <SelectItem value="pendente">Pendente</SelectItem>
              <SelectItem value="cancelada">Cancelada</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* List */}
        <div className="mt-6">
          {filtradas.length === 0 ? (
            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white py-16">
              <Inbox className="mb-3 h-12 w-12 text-slate-200" />
              <p className="text-slate-500">Nenhuma reserva {tab === "futuras" ? "futura" : "passada"} encontrada.</p>
            </div>
          ) : (
            <div className="space-y-3">
              {filtradas.map((r) => {
                const local = locais.find((l) => l.id === r.local_id);
                const camp = campi.find((c) => c.id === r.campi_id);
                const statusColor = { confirmada: "bg-green-100 text-green-700", pendente: "bg-amber-100 text-amber-700", cancelada: "bg-red-100 text-red-700" }[r.status];
                return (
                  <div key={r.id} className="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md">
                    <span className="h-10 w-1.5 shrink-0 rounded-full" style={{ backgroundColor: getCorTipo(r.tipo_local) }} />
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <h3 className="text-sm font-bold text-slate-900">{r.titulo}</h3>
                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${statusColor}`}>{r.status}</span>
                      </div>
                      <p className="text-xs text-slate-500">{camp?.nome} · {local?.nome}</p>
                      <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-400">
                        <span className="flex items-center gap-1"><CalendarDays className="h-3 w-3" />{format(parseISO(r.data_inicial), "dd/MM/yyyy", { locale: ptBR })} - {format(parseISO(r.data_final), "dd/MM/yyyy", { locale: ptBR })}</span>
                        <span>{r.horario_inicial} - {r.horario_final}</span>
                        <span className="truncate">{r.motivo}</span>
                      </div>
                    </div>
                    <Button variant="outline" size="sm" onClick={() => { setDetailReserva(r); setDetailOpen(true); }}>
                      <Eye className="h-4 w-4" /> Detalhes
                    </Button>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      <ReservationDetailModal
        reserva={detailReserva}
        open={detailOpen}
        onClose={() => setDetailOpen(false)}
        local={detailReserva ? locais.find((l) => l.id === detailReserva.local_id) : null}
        campi={detailReserva ? campi.find((c) => c.id === detailReserva.campi_id) : null}
        grupo={detailReserva ? grupos.find((g) => g.id === detailReserva.grupo_id) : null}
      />
    </div>
  );
}