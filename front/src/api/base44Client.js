// Substituto do SDK do Base44: expõe a mesma superfície (base44.auth.*, base44.entities.*.
// {list,create,update,delete,bulkCreate}), mas conversa com a API Laravel via fetch.

const TOKEN_KEY = "base44_access_token";
const API_BASE = "/api";

function getToken() {
  try {
    return typeof window !== "undefined" ? window.localStorage.getItem(TOKEN_KEY) : null;
  } catch {
    return null;
  }
}

function setToken(token) {
  try {
    if (typeof window === "undefined") return;
    if (token) window.localStorage.setItem(TOKEN_KEY, token);
    else window.localStorage.removeItem(TOKEN_KEY);
  } catch {
    /* ignore */
  }
}

function unwrap(payload) {
  if (payload && typeof payload === "object" && "data" in payload && Object.keys(payload).length === 1) {
    return payload.data;
  }
  return payload;
}

async function request(method, path, body) {
  const token = getToken();
  const headers = { Accept: "application/json" };
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (token) headers["Authorization"] = `Bearer ${token}`;

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  const contentType = res.headers.get("content-type") || "";
  const isJson = contentType.includes("application/json");
  const payload = isJson ? await res.json().catch(() => null) : null;

  if (!res.ok) {
    const err = new Error(payload?.message || `HTTP ${res.status}`);
    err.status = res.status;
    err.data = payload;
    throw err;
  }

  return unwrap(payload);
}

const auth = {
  async loginViaEmailPassword(email, password) {
    const r = await request("POST", "/auth/login", { email, password });
    if (r?.access_token) setToken(r.access_token);
    return r;
  },
  async loginWithProvider(provider, redirect) {
    return request("POST", `/auth/provider/${provider}`, { redirect });
  },
  async register({ email, password }) {
    return request("POST", "/auth/register", { email, password });
  },
  async verifyOtp({ email, otpCode }) {
    const r = await request("POST", "/auth/verify-otp", { email, otpCode });
    if (r?.access_token) setToken(r.access_token);
    return r;
  },
  async resendOtp(email) {
    return request("POST", "/auth/resend-otp", { email });
  },
  async resetPasswordRequest(email) {
    return request("POST", "/auth/forgot-password", { email });
  },
  async resetPassword({ resetToken, newPassword, email }) {
    const payload = { resetToken, newPassword };
    if (email) payload.email = email;
    return request("POST", "/auth/reset-password", payload);
  },
  async me() {
    return request("GET", "/auth/me");
  },
  async isAuthenticated() {
    if (!getToken()) return false;
    try {
      await request("GET", "/auth/me");
      return true;
    } catch {
      setToken(null);
      return false;
    }
  },
  async logout(redirect) {
    try {
      await request("POST", "/auth/logout");
    } catch {
      /* revoke may fail if token already invalid */
    }
    setToken(null);
    if (redirect && typeof window !== "undefined") {
      window.location.href = typeof redirect === "string" ? redirect : "/";
    }
  },
  setToken(token) {
    setToken(token);
  },
  redirectToLogin(from) {
    if (typeof window !== "undefined") {
      const suffix = from ? `?from=${encodeURIComponent(from)}` : "";
      window.location.href = `/login${suffix}`;
    }
  },
};

function makeEntity(path) {
  return {
    list: () => request("GET", `/${path}`),
    create: (data) => request("POST", `/${path}`, data),
    update: (id, data) => request("PUT", `/${path}/${id}`, data),
    delete: (id) => request("DELETE", `/${path}/${id}`),
  };
}

const Reserva = makeEntity("reservas");
Reserva.bulkCreate = (items) => request("POST", "/reservas/bulk", { reservas: items });
Reserva.aprovar = (id) => request("PATCH", `/reservas/${id}/aprovar`);
Reserva.cancelar = (id, motivo_cancelamento) =>
  request("PATCH", `/reservas/${id}/cancelar`, { motivo_cancelamento });
Reserva.pendentes = () => request("GET", "/reservas/pendentes");

const Local = makeEntity("locais");
Local.gerentes = (id) => request("GET", `/locais/${id}/gerentes`);
Local.setGerentes = (id, userIds) =>
  request("PUT", `/locais/${id}/gerentes`, { user_ids: userIds });
Local.indisponibilidades = (id) => request("GET", `/locais/${id}/indisponibilidades`);
Local.criarIndisponibilidade = (id, payload) => request("POST", `/locais/${id}/indisponibilidades`, payload);

const LocalIndisponibilidade = {
  update: (id, data) => request("PUT", `/indisponibilidades/${id}`, data),
  delete: (id) => request("DELETE", `/indisponibilidades/${id}`),
};

const Recurso = makeEntity("recursos");
Recurso.verificarDisponibilidade = (id, payload) =>
  request("POST", `/recursos/${id}/verificar-disponibilidade`, payload);
Recurso.agenda = (id) => request("GET", `/recursos/${id}/agenda`);
Recurso.disponiveis = (payload) => request("POST", "/recursos/disponiveis", payload);
Recurso.unidades = (id) => request("GET", `/recursos/${id}/unidades`);
Recurso.criarUnidade = (id, payload) => request("POST", `/recursos/${id}/unidades`, payload);
Recurso.atualizarUnidade = (id, unidadeId, payload) =>
  request("PATCH", `/recursos/${id}/unidades/${unidadeId}`, payload);
Recurso.previewRemocaoUnidade = (id, unidadeId) =>
  request("POST", `/recursos/${id}/unidades/${unidadeId}/preview-remocao`);
Recurso.confirmarRemocaoUnidade = (id, unidadeId, payload) =>
  request("POST", `/recursos/${id}/unidades/${unidadeId}/confirmar-remocao`, payload);
Recurso.relatorioReservas = (id, params = {}) =>
  request("GET", `/recursos/${id}/relatorio/reservas${qs(params)}`);
Recurso.relatorioOcupacao = (id, params = {}) =>
  request("GET", `/recursos/${id}/relatorio/ocupacao${qs(params)}`);
Recurso.relatorioUnidades = (id, params = {}) =>
  request("GET", `/recursos/${id}/relatorio/unidades${qs(params)}`);
Recurso.baixarCsv = async (id, tipo, params = {}) => {
  const token = getToken();
  const url = `${API_BASE}/recursos/${id}/relatorio/${tipo}${qs({ ...params, format: "csv" })}`;
  const res = await fetch(url, {
    headers: token ? { Authorization: `Bearer ${token}`, Accept: "text/csv" } : { Accept: "text/csv" },
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const blob = await res.blob();
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = `recurso-${id}-${tipo}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(a.href);
};

function qs(params) {
  const entries = Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== "");
  return entries.length ? "?" + new URLSearchParams(entries).toString() : "";
}

export const base44 = {
  auth,
  entities: {
    Campi: makeEntity("campi"),
    Grupo: makeEntity("grupos"),
    Local,
    Reserva,
    User: makeEntity("users"),
    Periodo: makeEntity("periodos"),
    LocalIndisponibilidade,
    Recurso,
  },
};
