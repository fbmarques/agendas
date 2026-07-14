import { format, parseISO } from "date-fns";
import { ptBR } from "date-fns/locale";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { getCorTipo } from "@/lib/tiposLocal";
import { MapPin, Calendar, Clock, User, FileText, Tag, Building2, Layers, Repeat, CheckCircle2, Clock3, XCircle } from "lucide-react";

export default function ReservationDetailModal({ reserva, local, campi, grupo, open, onClose }) {
  if (!reserva) return null;

  const Row = ({ icon: Icon, label, value }) => (
    <div className="flex items-start gap-3 border-b border-slate-100 py-2.5">
      <Icon className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
      <div className="flex-1">
        <p className="text-xs font-medium text-slate-400">{label}</p>
        <p className="text-sm font-semibold text-slate-800">{value || "—"}</p>
      </div>
    </div>
  );

  const statusInfo = {
    confirmada: { icon: CheckCircle2, color: "text-green-600 bg-green-50", label: "Confirmada" },
    pendente: { icon: Clock3, color: "text-amber-600 bg-amber-50", label: "Pendente" },
    cancelada: { icon: XCircle, color: "text-red-600 bg-red-50", label: "Cancelada" },
  };
  const si = statusInfo[reserva.status] || statusInfo.confirmada;

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <span className="h-3 w-3 rounded-full" style={{ backgroundColor: getCorTipo(reserva.tipo_local) }} />
            <DialogTitle className="text-lg">{reserva.titulo}</DialogTitle>
          </div>
        </DialogHeader>
        <div className="mt-2">
          <div className={`mb-4 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${si.color}`}>
            <si.icon className="h-3.5 w-3.5" />
            {si.label}
          </div>
          <div className="rounded-lg border border-slate-200 px-4">
            <Row icon={FileText} label="Motivo da reserva" value={reserva.motivo} />
            <Row icon={Building2} label="Campi" value={campi?.nome} />
            <Row icon={Layers} label="Grupo" value={grupo?.nome} />
            <Row icon={MapPin} label="Local" value={local?.nome} />
            <Row icon={Tag} label="Tipo do local" value={reserva.tipo_local} />
            <Row icon={Calendar} label="Data inicial" value={format(parseISO(reserva.data_inicial), "dd/MM/yyyy", { locale: ptBR })} />
            <Row icon={Calendar} label="Data final" value={format(parseISO(reserva.data_final), "dd/MM/yyyy", { locale: ptBR })} />
            <Row icon={Clock} label="Horário" value={`${reserva.horario_inicial} - ${reserva.horario_final}`} />
            <Row icon={Repeat} label="Recorrência" value={reserva.recorrente ? "Recorrente" : "Única"} />
            <Row icon={User} label="Responsável" value={reserva.responsavel_nome} />
            <Row icon={FileText} label="Observações" value={reserva.observacoes} />
            <Row icon={Clock3} label="Data de criação" value={reserva.created_date ? format(new Date(reserva.created_date), "dd/MM/yyyy 'às' HH:mm", { locale: ptBR }) : "—"} />
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}