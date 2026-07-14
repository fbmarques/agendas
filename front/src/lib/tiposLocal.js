// 11 tipos de local com cores únicas e distintas (sem preto e branco)
export const TIPOS_LOCAL = [
  { nome: "Sala de aula", cor: "#2563EB" },
  { nome: "Laboratório", cor: "#16A34A" },
  { nome: "Sala de reuniões", cor: "#9333EA" },
  { nome: "Auditório", cor: "#EA580C" },
  { nome: "Anfiteatro", cor: "#E11D48" },
  { nome: "Biblioteca", cor: "#0D9488" },
  { nome: "Espaço externo", cor: "#D97706" },
  { nome: "Sala administrativa", cor: "#4F46E5" },
  { nome: "Sala de estudo", cor: "#0891B2" },
  { nome: "Ginásio", cor: "#65A30D" },
  { nome: "Outro", cor: "#64748B" },
];

export function getCorTipo(nome) {
  const t = TIPOS_LOCAL.find((x) => x.nome === nome);
  return t ? t.cor : "#64748B";
}

export function getTipoObj(nome) {
  return TIPOS_LOCAL.find((x) => x.nome === nome) || TIPOS_LOCAL[TIPOS_LOCAL.length - 1];
}