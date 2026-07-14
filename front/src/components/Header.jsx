import { useEffect, useState } from "react";
import { Link, useNavigate, useLocation } from "react-router-dom";
import { base44 } from "@/api/base44Client";
import { LogOut, Menu, X } from "lucide-react";
import { Button } from "@/components/ui/button";

export default function Header() {
  const [user, setUser] = useState(null);
  const [checking, setChecking] = useState(true);
  const [mobileOpen, setMobileOpen] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    base44.auth.isAuthenticated().then((ok) => {
      if (ok) {
        base44.auth.me().then(setUser).catch(() => setUser(null));
      }
      setChecking(false);
    });
  }, [location.pathname]);

  const handleLogout = async () => {
    await base44.auth.logout();
    setUser(null);
    navigate("/");
  };

  const iniciais = user?.full_name
    ? user.full_name.split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase()
    : "?";

  const navLinks = [
    { to: "/", label: "Início" },
    { to: "/minhas-reservas", label: "Minhas Reservas", auth: true },
    { to: "/admin", label: "Painel Admin", auth: true },
  ];

  return (
    <header className="sticky top-0 z-50 w-full border-b border-slate-200 bg-white/90 backdrop-blur-md shadow-sm">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Link to="/" className="flex items-center gap-2.5">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-white shadow-md ring-1 ring-slate-200">
            <img src="/logo.png" alt="Logo" className="h-7 w-7 object-contain" />
          </div>
          <div className="flex flex-col leading-tight">
            <span className="text-sm font-bold tracking-tight text-slate-900">Reserva de Espaços</span>
            <span className="text-[10px] font-medium uppercase tracking-wider text-slate-400">Institucional</span>
          </div>
        </Link>

        <nav className="hidden items-center gap-1 md:flex">
          {navLinks.map((link) =>
            link.auth && !user ? null : (
              <Link
                key={link.to}
                to={link.to}
                className={`rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                  location.pathname === link.to
                    ? "bg-blue-50 text-blue-700"
                    : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                }`}
              >
                {link.label}
              </Link>
            )
          )}
        </nav>

        <div className="hidden items-center gap-3 md:flex">
          {!checking && !user ? (
            <>
              <Link to="/login">
                <Button variant="ghost" size="sm">Entrar</Button>
              </Link>
              <Link to="/register">
                <Button size="sm" className="bg-blue-600 hover:bg-blue-700">Cadastrar</Button>
              </Link>
            </>
          ) : user ? (
            <div className="flex items-center gap-3">
              <div className="flex items-center gap-2 rounded-full bg-slate-100 py-1 pl-1 pr-3">
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-xs font-bold text-white">
                  {iniciais}
                </div>
                <span className="text-sm font-medium text-slate-700">{user.full_name || user.email}</span>
              </div>
              <Button variant="ghost" size="sm" onClick={handleLogout} className="text-slate-500 hover:text-red-600">
                <LogOut className="h-4 w-4" />
              </Button>
            </div>
          ) : null}
        </div>

        <button className="md:hidden" onClick={() => setMobileOpen(!mobileOpen)}>
          {mobileOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
        </button>
      </div>

      {mobileOpen && (
        <div className="border-t border-slate-200 bg-white px-4 py-3 md:hidden">
          <div className="flex flex-col gap-1">
            {navLinks.map((link) =>
              link.auth && !user ? null : (
                <Link key={link.to} to={link.to} onClick={() => setMobileOpen(false)} className="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                  {link.label}
                </Link>
              )
            )}
            <div className="mt-2 border-t border-slate-100 pt-2">
              {!checking && !user ? (
                <div className="flex gap-2">
                  <Link to="/login" className="flex-1"><Button variant="outline" size="sm" className="w-full">Entrar</Button></Link>
                  <Link to="/register" className="flex-1"><Button size="sm" className="w-full bg-blue-600">Cadastrar</Button></Link>
                </div>
              ) : user ? (
                <Button variant="outline" size="sm" className="w-full" onClick={handleLogout}>Sair</Button>
              ) : null}
            </div>
          </div>
        </div>
      )}
    </header>
  );
}