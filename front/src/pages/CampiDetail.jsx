import { useEffect, useState, useMemo } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { base44 } from "@/api/base44Client";
import { format } from "date-fns";
import Header from "@/components/Header";
import Agenda from "@/components/Agenda";
import ColorLegend from "@/components/ColorLegend";
import ReservationDetailModal from "@/components/ReservationDetailModal";
import ReservationModal from "@/components/ReservationModal";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Search, MapPin, ArrowLeft, Plus, Grid3x3, List } from "lucide-react";
import { getCorTipo } from "@/lib/tiposLocal";
import { TIPOS_LOCAL } from "@/lib/tiposLocal";

export default function CampiDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [campi, setCampi] = useState(null);
  const [grupos, setGrupos] = useState([]);
  const [locais, setLocais] = useState([]);
  const [reservas, setReservas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState(null);

  const [grupoSelecionado, setGrupoSelecionado] = useState(null);
  const [localSelecionado, setLocalSelecionado] = useState(null);
  const [filtroTipo, setFiltroTipo] = useState("todos");
  const [busca, setBusca] = useState("");
  const [viewMode, setViewMode] = useState("grid");

  const [modoAgenda, setModoAgenda] = useState("mes");
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [detailReserva, setDetailReserva] = useState(null);
  const [detailOpen, setDetailOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [preData, setPreData] = useState("");

  useEffect(() => {
    Promise.all([
      base44.entities.Campi.list(),
      base44.entities.Grupo.list(),
      base44.entities.Local.list(),
      base44.entities.Reserva.list(),
    ]).then(([cs, gs, ls, rs]) => {
      setCampi((cs || []).find((c) => c.id === id));
      setGrupos((gs || []).filter((g) => g.campi_id === id));
      setLocais((ls || []).filter((l) => l.campi_id === id));
      setReservas(rs || []);
      setLoading(false);
    }).catch(() => setLoading(false));
    base44.auth.me().then(setUser).catch(() => {});
  }, [id]);

  const gruposCampi = grupos;
  const locaisCampi = useMemo(() => {
    return locais.filter((l) => {
      if (filtroTipo !== "todos" && l.tipo !== filtroTipo) return false;
      if (busca && !l.nome.toLowerCase().includes(busca.toLowerCase())) return false;
      return true;
    });
  }, [locais, filtroTipo, busca]);

  const locaisDoGrupo = useMemo(() => {
    if (!grupoSelecionado) return [];
    return locais.filter((l) => l.grupo_id === grupoSelecionado.id);
  }, [locais, grupoSelecionado]);

  // Agenda: if local selected show its reservations, if grupo selected show consolidated
  const reservasAgenda = useMemo(() => {
    if (localSelecionado) return reservas.filter((r) => r.local_id === localSelecionado.id);
    if (grupoSelecionado) {
      const ids = locaisDoGrupo.map((l) => l.id);
      return reservas.filter((r) => ids.includes(r.local_id));
    }
    return reservas.filter((r) => r.campi_id === id);
  }, [reservas, localSelecionado, grupoSelecionado, locaisDoGrupo, id]);

  const locaisAgenda = localSelecionado ? [localSelecionado] : (grupoSelecionado ? locaisDoGrupo : locaisCampi);

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

  if (!campi) {
    return (
      <div className="min-h-screen bg-slate-50">
        <Header />
        <div className="flex flex-col items-center justify-center py-20">
          <p className="text-slate-500">Campi não encontrado.</p>
          <Button className="mt-4 bg-blue-600" onClick={() => navigate("/")}>Voltar ao início</Button>
        </div>
      </div>
    );
  }

  const openDetail = (r) => {
    setDetailReserva(r);
    setDetailOpen(true);
  };

  const handleReservar = (dia) => {
    if (!user) {
      navigate("/login");
      return;
    }
    setPreData(dia ? format(dia, "yyyy-MM-dd") : "");
    setCreateOpen(true);
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <Header />

      {/* Campi header */}
      <section className="border-b border-slate-200 bg-white">
        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
          <button onClick={() => navigate("/")} className="mb-3 flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
            <ArrowLeft className="h-4 w-4" /> Voltar
          </button>
          <div className="flex items-center gap-4">
            <span className="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-lg font-bold text-white shadow-lg">{campi.sigla}</span>
            <div>
              <h1 className="text-2xl font-bold text-slate-900">{campi.nome}</h1>
              <p className="text-sm text-slate-500">{campi.endereco} · {campi.cidade}</p>
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">
        {/* Filters */}
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar local por nome..." className="pl-9" />
          </div>
          <Select value={filtroTipo} onValueChange={setFiltroTipo}>
            <SelectTrigger className="sm:w-56"><SelectValue placeholder="Tipo de local" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="todos">Todos os tipos</SelectItem>
              {TIPOS_LOCAL.map((t) => <SelectItem key={t.nome} value={t.nome}>{t.nome}</SelectItem>)}
            </SelectContent>
          </Select>
          <div className="flex items-center gap-1 rounded-lg bg-slate-100 p-1">
            <button onClick={() => setViewMode("grid")} className={`rounded-md p-1.5 ${viewMode === "grid" ? "bg-white shadow-sm" : ""}`}><Grid3x3 className="h-4 w-4" /></button>
            <button onClick={() => setViewMode("list")} className={`rounded-md p-1.5 ${viewMode === "list" ? "bg-white shadow-sm" : ""}`}><List className="h-4 w-4" /></button>
          </div>
        </div>

        {/* Locais grid/list */}
        <div className="mb-8">
          <h2 className="mb-3 text-lg font-bold text-slate-900">
            {grupoSelecionado ? `Locais de ${grupoSelecionado.nome}` : "Locais disponíveis"}
          </h2>
          {locaisCampi.length === 0 ? (
            <div className="rounded-xl border border-dashed border-slate-200 bg-white py-12 text-center">
              <MapPin className="mx-auto mb-2 h-10 w-10 text-slate-200" />
              <p className="text-sm text-slate-400">Nenhum local encontrado com os filtros atuais.</p>
            </div>
          ) : viewMode === "grid" ? (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {locaisCampi.map((l) => {
                const numReservas = reservas.filter((r) => r.local_id === l.id).length;
                return (
                  <div
                    key={l.id}
                    onClick={() => { setLocalSelecionado(l); setGrupoSelecionado(null); }}
                    className={`cursor-pointer rounded-xl border bg-white p-4 shadow-sm transition-all hover:shadow-md ${localSelecionado?.id === l.id ? "border-blue-500 ring-2 ring-blue-100" : "border-slate-200"}`}
                  >
                    <div className="mb-2 flex items-start justify-between">
                      <div className="flex items-center gap-2">
                        <span className="h-3 w-3 rounded-full" style={{ backgroundColor: getCorTipo(l.tipo) }} />
                        <h3 className="text-sm font-bold text-slate-900">{l.nome}</h3>
                      </div>
                    </div>
                    <span className="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold" style={{ backgroundColor: getCorTipo(l.tipo) + "22", color: getCorTipo(l.tipo) }}>{l.tipo}</span>
                    <p className="mt-2 line-clamp-2 text-xs text-slate-500">{l.descricao}</p>
                    <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-2">
                      <span className="text-xs text-slate-400">Cap: {l.capacidade}</span>
                      <span className="text-xs text-slate-400">{numReservas} reservas</span>
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
              <table className="w-full text-sm">
                <thead className="bg-slate-50 text-xs uppercase text-slate-400">
                  <tr>
                    <th className="px-4 py-3 text-left font-semibold">Local</th>
                    <th className="px-4 py-3 text-left font-semibold">Tipo</th>
                    <th className="px-4 py-3 text-left font-semibold">Capacidade</th>
                    <th className="px-4 py-3 text-left font-semibold">Reservas</th>
                  </tr>
                </thead>
                <tbody>
                  {locaisCampi.map((l) => (
                    <tr key={l.id} onClick={() => { setLocalSelecionado(l); setGrupoSelecionado(null); }} className={`cursor-pointer border-t border-slate-100 hover:bg-slate-50 ${localSelecionado?.id === l.id ? "bg-blue-50" : ""}`}>
                      <td className="px-4 py-3 font-medium text-slate-800">
                        <span className="mr-2 inline-block h-2.5 w-2.5 rounded-full align-middle" style={{ backgroundColor: getCorTipo(l.tipo) }} />
                        {l.nome}
                      </td>
                      <td className="px-4 py-3 text-slate-600">{l.tipo}</td>
                      <td className="px-4 py-3 text-slate-600">{l.capacidade}</td>
                      <td className="px-4 py-3 text-slate-600">{reservas.filter((r) => r.local_id === l.id).length}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Agenda */}
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-bold text-slate-900">
            {localSelecionado ? `Agenda: ${localSelecionado.nome}` : grupoSelecionado ? `Agenda Consolidada: ${grupoSelecionado.nome}` : "Agenda do Campi"}
          </h2>
          <div className="flex items-center gap-2">
            {localSelecionado && (
              <Button size="sm" variant="outline" onClick={() => setLocalSelecionado(null)}>Ver todos os locais</Button>
            )}
            <Button size="sm" className="bg-blue-600 hover:bg-blue-700" onClick={() => handleReservar(null)}>
              <Plus className="h-4 w-4" /> Reservar
            </Button>
          </div>
        </div>
        <Agenda
          reservas={reservasAgenda}
          locais={locaisAgenda}
          onReservaClick={openDetail}
          onCreateReserva={handleReservar}
          selectedDate={selectedDate}
          setSelectedDate={setSelectedDate}
          modo={modoAgenda}
          setModo={setModoAgenda}
        />

        {/* Legend */}
        <div className="mt-6">
          <ColorLegend />
        </div>
      </div>

      <ReservationDetailModal
        reserva={detailReserva}
        open={detailOpen}
        onClose={() => setDetailOpen(false)}
        local={detailReserva ? locais.find((l) => l.id === detailReserva.local_id) : null}
        campi={campi}
        grupo={detailReserva ? grupos.find((g) => g.id === detailReserva.grupo_id) : null}
      />
      <ReservationModal
        open={createOpen}
        onClose={() => { setCreateOpen(false); setPreData(""); }}
        onCreated={() => { base44.entities.Reserva.list().then(setReservas); }}
        campi={[campi]}
        grupos={gruposCampi}
        locais={locais}
        reservasExistentes={reservas}
        preCampiId={campi.id}
        preGrupoId={grupoSelecionado?.id}
        preLocalId={localSelecionado?.id}
        preData={preData}
      />
    </div>
  );
}