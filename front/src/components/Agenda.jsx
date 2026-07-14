import { useState, useMemo } from "react";
import {
  format, addMonths, subMonths, startOfMonth, endOfMonth, startOfWeek, endOfWeek, endOfDay,
  eachDayOfInterval, isSameMonth, addDays, subDays, addWeeks, subWeeks,
  startOfWeek as sow, endOfWeek as eow, startOfDay, isToday, parseISO
} from "date-fns";
import { ptBR } from "date-fns/locale";
import { ChevronLeft, ChevronRight, CalendarDays } from "lucide-react";
import { Button } from "@/components/ui/button";
import { getCorTipo } from "@/lib/tiposLocal";

export default function Agenda({ reservas = [], locais = [], onReservaClick, onCreateReserva, selectedDate, setSelectedDate, modo, setModo }) {
  const [currentDate, setCurrentDate] = useState(selectedDate || new Date());
  const [tooltip, setTooltip] = useState(null);

  const tiposUsados = useMemo(() => {
    const set = new Set();
    reservas.forEach((r) => set.add(r.tipo_local));
    return [...set];
  }, [reservas]);

  const reservasDoDia = (dia) =>
    reservas.filter((r) => {
      const ini = parseISO(r.data_inicial);
      const fim = parseISO(r.data_final);
      return dia >= startOfDay(ini) && dia <= endOfDay(fim);
    });

  const navegar = (dir) => {
    if (modo === "mes") setCurrentDate(dir > 0 ? addMonths(currentDate, 1) : subMonths(currentDate, 1));
    else if (modo === "semana") setCurrentDate(dir > 0 ? addWeeks(currentDate, 1) : subWeeks(currentDate, 1));
    else setCurrentDate(dir > 0 ? addDays(currentDate, 1) : subDays(currentDate, 1));
  };

  const tituloPeriodo = () => {
    if (modo === "mes") return format(currentDate, "MMMM 'de' yyyy", { locale: ptBR });
    if (modo === "semana") {
      const s = sow(currentDate, { weekStartsOn: 0 });
      const e = eow(currentDate, { weekStartsOn: 0 });
      return `${format(s, "dd/MM", { locale: ptBR })} - ${format(e, "dd/MM/yyyy", { locale: ptBR })}`;
    }
    return format(currentDate, "EEEE, dd 'de' MMMM", { locale: ptBR });
  };

  const horas = Array.from({ length: 14 }, (_, i) => i + 7); // 7h - 20h

  return (
    <div className="rounded-xl border border-slate-200 bg-white shadow-sm">
      {/* Toolbar */}
      <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <Button variant="outline" size="icon" onClick={() => navegar(-1)}>
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <Button variant="outline" size="icon" onClick={() => navegar(1)}>
            <ChevronRight className="h-4 w-4" />
          </Button>
          <h3 className="text-lg font-bold capitalize text-slate-900">{tituloPeriodo()}</h3>
          <Button variant="ghost" size="sm" onClick={() => setCurrentDate(new Date())} className="text-slate-500">
            Hoje
          </Button>
        </div>
        <div className="flex items-center gap-1 rounded-lg bg-slate-100 p-1">
          {["mes", "semana", "dia"].map((m) => (
            <button
              key={m}
              onClick={() => setModo(m)}
              className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                modo === m ? "bg-white text-blue-700 shadow-sm" : "text-slate-500 hover:text-slate-700"
              }`}
            >
              {m === "mes" ? "Mês" : m === "semana" ? "Semana" : "Dia"}
            </button>
          ))}
        </div>
      </div>

      {/* Month view */}
      {modo === "mes" && (
        <div className="p-2 sm:p-4">
          <div className="grid grid-cols-7 gap-1">
            {["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"].map((d) => (
              <div key={d} className="pb-2 text-center text-xs font-semibold uppercase text-slate-400">{d}</div>
            ))}
          </div>
          <div className="grid grid-cols-7 gap-1">
            {eachDayOfInterval({
              start: startOfWeek(startOfMonth(currentDate), { weekStartsOn: 0 }),
              end: endOfWeek(endOfMonth(currentDate), { weekStartsOn: 0 }),
            }).map((dia) => {
              const rs = reservasDoDia(dia);
              const inMonth = isSameMonth(dia, currentDate);
              const today = isToday(dia);
              return (
                <div
                  key={dia.toISOString()}
                  onClick={() => onCreateReserva?.(dia)}
                  className={`min-h-[80px] cursor-pointer rounded-lg border p-1 transition-colors hover:border-blue-300 hover:bg-blue-50/40 sm:min-h-[110px] ${
                    inMonth ? "border-slate-100 bg-white" : "border-slate-50 bg-slate-50/50"
                  } ${today ? "ring-2 ring-blue-200" : ""}`}
                >
                  <div className={`mb-1 text-right text-xs font-semibold ${today ? "flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-white ml-auto" : inMonth ? "text-slate-700" : "text-slate-300"}`}>
                    {format(dia, "d")}
                  </div>
                  <div className="space-y-0.5">
                    {rs.slice(0, 3).map((r) => {
                      const local = locais.find((l) => l.id === r.local_id);
                      return (
                        <div
                          key={r.id}
                          onClick={(e) => { e.stopPropagation(); onReservaClick?.(r); }}
                          onMouseEnter={(e) => {
                            const rect = e.currentTarget.getBoundingClientRect();
                            setTooltip({ reserva: r, local, x: rect.left, y: rect.top });
                          }}
                          onMouseLeave={() => setTooltip(null)}
                          className="cursor-pointer truncate rounded px-1 py-0.5 text-[10px] font-medium text-white hover:opacity-80"
                          style={{ backgroundColor: getCorTipo(r.tipo_local) }}
                        >
                          {r.horario_inicial} {r.titulo}
                        </div>
                      );
                    })}
                    {rs.length > 3 && <div className="px-1 text-[10px] text-slate-400">+{rs.length - 3} mais</div>}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Week view */}
      {modo === "semana" && (
        <div className="overflow-x-auto p-2 sm:p-4">
          <div className="min-w-[700px]">
            <div className="grid" style={{ gridTemplateColumns: "60px repeat(7, 1fr)" }}>
              <div></div>
              {eachDayOfInterval({
                start: sow(currentDate, { weekStartsOn: 0 }),
                end: eow(currentDate, { weekStartsOn: 0 }),
              }).map((dia) => (
                <div key={dia.toISOString()} className="border-b border-slate-200 pb-2 text-center">
                  <span className="text-xs font-semibold uppercase text-slate-400">{format(dia, "EEE", { locale: ptBR })}</span>
                  <div className={`mx-auto mt-1 flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold ${isToday(dia) ? "bg-blue-600 text-white" : "text-slate-700"}`}>
                    {format(dia, "d")}
                  </div>
                </div>
              ))}
            </div>
            <div className="relative">
              {horas.map((h) => (
                <div key={h} className="grid border-t border-slate-100" style={{ gridTemplateColumns: "60px repeat(7, 1fr)", height: "48px" }}>
                  <div className="pr-2 text-right text-[10px] font-medium text-slate-400">{String(h).padStart(2, "0")}:00</div>
                  {eachDayOfInterval({
                    start: sow(currentDate, { weekStartsOn: 0 }),
                    end: eow(currentDate, { weekStartsOn: 0 }),
                  }).map((dia) => {
                    const rs = reservasDoDia(dia).filter((r) => {
                      const hi = parseInt(r.horario_inicial.split(":")[0]);
                      return hi === h;
                    });
                    return (
                      <div key={dia.toISOString()} className="relative border-l border-slate-100">
                        {rs.map((r) => {
                          const local = locais.find((l) => l.id === r.local_id);
                          const hi = parseInt(r.horario_inicial.split(":")[0]);
                          const mi = parseInt(r.horario_inicial.split(":")[1]);
                          const hf = parseInt(r.horario_final.split(":")[0]);
                          const mf = parseInt(r.horario_final.split(":")[1]);
                          const top = ((mi / 60) * 48);
                          const height = Math.max(20, ((hf - hi) + (mf - mi) / 60) * 48 - 2);
                          return (
                            <div
                              key={r.id}
                              onClick={() => onReservaClick?.(r)}
                              onMouseEnter={(e) => { const rect = e.currentTarget.getBoundingClientRect(); setTooltip({ reserva: r, local, x: rect.left, y: rect.top }); }}
                              onMouseLeave={() => setTooltip(null)}
                              className="absolute left-0.5 right-0.5 cursor-pointer overflow-hidden rounded px-1.5 py-0.5 text-[10px] font-medium text-white hover:opacity-80"
                              style={{ backgroundColor: getCorTipo(r.tipo_local), top, height }}
                            >
                              {r.titulo}
                            </div>
                          );
                        })}
                      </div>
                    );
                  })}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Day view */}
      {modo === "dia" && (
        <div className="p-2 sm:p-4">
          <div className="mx-auto max-w-2xl">
            {horas.map((h) => {
              const rs = reservasDoDia(currentDate).filter((r) => parseInt(r.horario_inicial.split(":")[0]) === h);
              return (
                <div key={h} className="flex gap-3 border-t border-slate-100" style={{ minHeight: "56px" }}>
                  <div className="w-14 shrink-0 pt-1 text-right text-xs font-medium text-slate-400">{String(h).padStart(2, "0")}:00</div>
                  <div className="relative flex-1 py-1">
                    {rs.map((r) => {
                      const local = locais.find((l) => l.id === r.local_id);
                      const mi = parseInt(r.horario_inicial.split(":")[1]);
                      const hf = parseInt(r.horario_final.split(":")[0]);
                      const mf = parseInt(r.horario_final.split(":")[1]);
                      const height = Math.max(30, ((hf - h) + (mf - mi) / 60) * 56 - 4);
                      return (
                        <div
                          key={r.id}
                          onClick={() => onReservaClick?.(r)}
                          onMouseEnter={(e) => { const rect = e.currentTarget.getBoundingClientRect(); setTooltip({ reserva: r, local, x: rect.left, y: rect.top }); }}
                          onMouseLeave={() => setTooltip(null)}
                          className="mb-1 cursor-pointer rounded-lg px-3 py-2 text-white hover:opacity-90"
                          style={{ backgroundColor: getCorTipo(r.tipo_local), height }}
                        >
                          <p className="text-sm font-semibold">{r.titulo}</p>
                          <p className="text-xs opacity-90">{r.horario_inicial} - {r.horario_final}</p>
                          {local && <p className="text-xs opacity-75">{local.nome} · {r.tipo_local}</p>}
                          <p className="text-xs opacity-75">{r.responsavel_nome}</p>
                        </div>
                      );
                    })}
                  </div>
                </div>
              );
            })}
            {reservasDoDia(currentDate).length === 0 && (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <CalendarDays className="mb-2 h-10 w-10 text-slate-200" />
                <p className="text-sm text-slate-400">Nenhuma reserva neste dia.</p>
                {onCreateReserva && <Button size="sm" className="mt-3 bg-blue-600 hover:bg-blue-700" onClick={onCreateReserva}>Criar reserva</Button>}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Tooltip popup */}
      {tooltip && (
        <div
          className="pointer-events-none fixed z-[60] max-w-xs rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
          style={{ left: Math.min(tooltip.x, window.innerWidth - 280), top: tooltip.y - 10 }}
        >
          <div className="mb-1 flex items-center gap-2">
            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: getCorTipo(tooltip.reserva.tipo_local) }} />
            <span className="text-sm font-bold text-slate-900">{tooltip.reserva.titulo}</span>
          </div>
          <div className="space-y-0.5 text-xs text-slate-600">
            <p><span className="font-medium">Local:</span> {tooltip.local?.nome || "—"}</p>
            <p><span className="font-medium">Tipo:</span> {tooltip.reserva.tipo_local}</p>
            <p><span className="font-medium">Período:</span> {format(parseISO(tooltip.reserva.data_inicial), "dd/MM/yyyy")} a {format(parseISO(tooltip.reserva.data_final), "dd/MM/yyyy")}</p>
            <p><span className="font-medium">Horário:</span> {tooltip.reserva.horario_inicial} - {tooltip.reserva.horario_final}</p>
            <p><span className="font-medium">Responsável:</span> {tooltip.reserva.responsavel_nome}</p>
            <p><span className="font-medium">Recorrência:</span> {tooltip.reserva.recorrente ? "Recorrente" : "Única"}</p>
          </div>
        </div>
      )}
    </div>
  );
}