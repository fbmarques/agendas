import { TIPOS_LOCAL } from "@/lib/tiposLocal";

export default function ColorLegend({ compact }) {
  if (compact) {
    return (
      <div className="flex flex-wrap gap-x-4 gap-y-1.5">
        {TIPOS_LOCAL.map((t) => (
          <div key={t.nome} className="flex items-center gap-1.5">
            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: t.cor }} />
            <span className="text-xs text-slate-600">{t.nome}</span>
          </div>
        ))}
      </div>
    );
  }
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <h4 className="mb-3 text-sm font-semibold text-slate-800">Legenda de Tipos</h4>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {TIPOS_LOCAL.map((t) => (
          <div key={t.nome} className="flex items-center gap-2">
            <span className="h-3 w-3 rounded-full ring-1 ring-black/5" style={{ backgroundColor: t.cor }} />
            <span className="text-xs font-medium text-slate-600">{t.nome}</span>
          </div>
        ))}
      </div>
    </div>
  );
}