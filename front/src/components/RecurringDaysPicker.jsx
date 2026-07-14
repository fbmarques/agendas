import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const DIAS = [
  { idx: 1, label: "Segunda" },
  { idx: 2, label: "Terça" },
  { idx: 3, label: "Quarta" },
  { idx: 4, label: "Quinta" },
  { idx: 5, label: "Sexta" },
  { idx: 6, label: "Sábado" },
  { idx: 0, label: "Domingo" },
];

export default function RecurringDaysPicker({ value = {}, onChange }) {
  const toggle = (idx) => {
    onChange({ ...value, [idx]: value[idx] ? undefined : { horario_inicial: "", horario_final: "" } });
  };
  const updateTime = (idx, field, v) => {
    onChange({ ...value, [idx]: { ...value[idx], [field]: v } });
  };

  return (
    <div>
      <Label className="mb-1.5">Dias da semana e horários *</Label>
      <p className="mb-2 text-xs text-slate-400">Selecione os dias e defina o horário de cada um.</p>
      <div className="grid gap-2 sm:grid-cols-2">
        {DIAS.map((d) => {
          const active = !!value[d.idx];
          return (
            <div key={d.idx} className={`rounded-lg border p-2.5 ${active ? "border-blue-300 bg-blue-50/40" : "border-slate-200"}`}>
              <button
                type="button"
                onClick={() => toggle(d.idx)}
                className="flex w-full items-center gap-2 text-sm font-medium text-slate-700"
              >
                <span className={`flex h-4 w-4 items-center justify-center rounded border text-[10px] ${active ? "border-blue-600 bg-blue-600 text-white" : "border-slate-300 bg-white"}`}>
                  {active ? "✓" : ""}
                </span>
                {d.label}
              </button>
              {active && (
                <div className="mt-2 flex items-center gap-2 pl-6">
                  <Input
                    type="time"
                    lang="pt-BR"
                    step="60"
                    value={value[d.idx].horario_inicial}
                    onChange={(e) => updateTime(d.idx, "horario_inicial", e.target.value)}
                    className="h-8 w-28"
                  />
                  <span className="text-xs text-slate-400">até</span>
                  <Input
                    type="time"
                    lang="pt-BR"
                    step="60"
                    value={value[d.idx].horario_final}
                    onChange={(e) => updateTime(d.idx, "horario_final", e.target.value)}
                    className="h-8 w-28"
                  />
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}