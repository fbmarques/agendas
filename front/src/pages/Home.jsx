import { useEffect, useState } from "react";
import { base44 } from "@/api/base44Client";
import Header from "@/components/Header";
import CampusGeometric from "@/components/CampusGeometric";
import ColorLegend from "@/components/ColorLegend";
import { Building2 } from "lucide-react";

export default function Home() {
  const [campi, setCampi] = useState([]);
  const [grupos, setGrupos] = useState([]);
  const [locais, setLocais] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      base44.entities.Campi.list(),
      base44.entities.Grupo.list(),
      base44.entities.Local.list(),
    ]).then(([c, g, l]) => {
      setCampi(c || []);
      setGrupos(g || []);
      setLocais(l || []);
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

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

      {/* Geometric composition */}
      <section className="mx-auto max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
        <CampusGeometric campi={campi} grupos={grupos} locais={locais} />
      </section>

      {/* Color legend */}
      <section className="mx-auto max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
        <ColorLegend />
      </section>

      {/* Footer */}
      <footer className="border-t border-slate-200 bg-white">
        <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-6 sm:flex-row sm:px-6 lg:px-8">
          <div className="flex items-center gap-2 text-sm text-slate-500">
            <Building2 className="h-4 w-4" /> Sistema de Reserva de Espaços Institucionais
          </div>
          <p className="text-xs text-slate-400">© 2026 Instituição. Todos os direitos reservados.</p>
        </div>
      </footer>
    </div>
  );
}