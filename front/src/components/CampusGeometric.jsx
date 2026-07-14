import { Building2 } from "lucide-react";
import { useState } from "react";
import { Link } from "react-router-dom";

export default function CampusGeometric({ campi, grupos, locais }) {
  const [hovered, setHovered] = useState(null);
  const count = campi.length;

  if (count === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <Building2 className="mb-4 h-12 w-12 text-slate-300" />
        <p className="text-slate-500">Nenhum campi cadastrado ainda.</p>
      </div>
    );
  }

  const cardW = 224; // w-56
  const cardH = 150;
  const minGap = 28;

  // Dynamic radius large enough so adjacent cards never overlap.
  const minRadius = count <= 2 ? 0 : (cardW + minGap) / (2 * Math.sin(Math.PI / count));
  const radius = Math.max(minRadius, 180);
  const pad = 40;
  const W = count <= 2 ? 0 : Math.round(2 * radius + cardW + pad);
  const H = count <= 2 ? 0 : Math.round(2 * radius + cardH + pad);
  const cx = W / 2;
  const cy = H / 2;

  const pos = (i) => {
    const angle = (360 / count) * i - 90;
    const rad = (angle * Math.PI) / 180;
    return { x: Math.cos(rad) * radius, y: Math.sin(rad) * radius };
  };

  const renderCard = (c) => {
    const numGrupos = grupos.filter((g) => g.campi_id === c.id).length;
    const numLocais = locais.filter((l) => l.campi_id === c.id).length;
    const ativo = c.status === "ativo";
    return (
      <Link
        to={`/campi/${c.id}`}
        onMouseEnter={() => setHovered(c.id)}
        onMouseLeave={() => setHovered(null)}
        className={`block w-56 rounded-2xl border bg-white p-4 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-xl ${
          hovered === c.id ? "border-blue-400 ring-2 ring-blue-100" : "border-slate-200"
        }`}
      >
        <div className="mb-2 flex items-start justify-between">
          <div className="flex items-center gap-2">
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-sm font-bold text-blue-700">
              {c.sigla}
            </span>
            <div>
              <h3 className="text-sm font-bold leading-tight text-slate-900">{c.nome}</h3>
              <p className="text-[11px] text-slate-400">{c.cidade}</p>
            </div>
          </div>
          <span className={`h-2.5 w-2.5 rounded-full ${ativo ? "bg-green-500" : "bg-red-400"}`} />
        </div>
        <p className="mb-3 line-clamp-2 text-xs text-slate-500">{c.descricao}</p>
        <div className="flex gap-3 border-t border-slate-100 pt-2">
          <div className="flex flex-col">
            <span className="text-base font-bold text-slate-900">{numGrupos}</span>
            <span className="text-[10px] text-slate-400">Grupos</span>
          </div>
          <div className="flex flex-col">
            <span className="text-base font-bold text-slate-900">{numLocais}</span>
            <span className="text-[10px] text-slate-400">Locais</span>
          </div>
        </div>
      </Link>
    );
  };

  return (
    <div className="flex justify-center">
      {/* Desktop / tablet: geometric composition */}
      {count <= 2 ? (
        <div className="hidden items-center justify-center gap-8 sm:flex">
          <div className="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-2xl ring-8 ring-white">
            <Building2 className="h-7 w-7" />
            <span className="mt-0.5 text-[8px] font-bold uppercase tracking-widest">Instituição</span>
          </div>
          {campi.map((c) => renderCard(c))}
        </div>
      ) : (
        <div
          className="relative hidden items-center justify-center sm:flex"
          style={{ width: W, height: H }}
        >
          {/* Central logo */}
          <div className="absolute z-10 flex h-28 w-28 flex-col items-center justify-center rounded-full bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-2xl ring-8 ring-white"
            style={{ left: cx, top: cy, transform: "translate(-50%, -50%)" }}>
            <Building2 className="h-8 w-8" />
            <span className="mt-1 text-[9px] font-bold uppercase tracking-widest">Instituição</span>
          </div>

          {/* Connection lines via SVG */}
          <svg className="absolute inset-0" style={{ pointerEvents: "none" }} width={W} height={H}>
            {campi.map((c, i) => {
              const { x, y } = pos(i);
              return (
                <line
                  key={c.id}
                  x1={cx}
                  y1={cy}
                  x2={cx + x}
                  y2={cy + y}
                  stroke={hovered === c.id ? "#3b82f6" : "#cbd5e1"}
                  strokeWidth={hovered === c.id ? 2 : 1}
                  strokeDasharray="4 4"
                  className="transition-all"
                />
              );
            })}
          </svg>

          {/* Campus cards positioned around center */}
          {campi.map((c, i) => {
            const { x, y } = pos(i);
            return (
              <div
                key={c.id}
                className="absolute z-20"
                style={{ left: cx + x, top: cy + y, transform: "translate(-50%, -50%)" }}
              >
                {renderCard(c)}
              </div>
            );
          })}
        </div>
      )}

      {/* Mobile: vertical carousel */}
      <div className="flex w-full snap-x snap-mandatory gap-4 overflow-x-auto px-1 pb-2 sm:hidden">
        <div className="flex shrink-0 flex-col items-center justify-center">
          <div className="flex h-20 w-20 flex-col items-center justify-center rounded-full bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-lg">
            <Building2 className="h-6 w-6" />
          </div>
        </div>
        {campi.map((c) => {
          const numGrupos = grupos.filter((g) => g.campi_id === c.id).length;
          const numLocais = locais.filter((l) => l.campi_id === c.id).length;
          return (
            <Link key={c.id} to={`/campi/${c.id}`} className="block w-64 shrink-0 snap-center rounded-2xl border border-slate-200 bg-white p-4 shadow-md">
              <div className="mb-2 flex items-center justify-between">
                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-sm font-bold text-blue-700">{c.sigla}</span>
                <span className={`h-2.5 w-2.5 rounded-full ${c.status === "ativo" ? "bg-green-500" : "bg-red-400"}`} />
              </div>
              <h3 className="text-sm font-bold text-slate-900">{c.nome}</h3>
              <p className="text-[11px] text-slate-400">{c.cidade}</p>
              <p className="mt-2 line-clamp-2 text-xs text-slate-500">{c.descricao}</p>
              <div className="mt-3 flex gap-4 border-t border-slate-100 pt-2">
                <span className="text-sm font-bold text-slate-900">{numGrupos} grupos</span>
                <span className="text-sm font-bold text-slate-900">{numLocais} locais</span>
              </div>
            </Link>
          );
        })}
      </div>
    </div>
  );
}