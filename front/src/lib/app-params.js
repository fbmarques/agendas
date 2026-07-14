// Antes: guardava appId/token do Base44. Agora só expõe utilitários mínimos
// para compatibilidade com códigos existentes que possam importar.

const TOKEN_KEY = "base44_access_token";

export const appParams = {
  get token() {
    try {
      return typeof window !== "undefined" ? window.localStorage.getItem(TOKEN_KEY) : null;
    } catch {
      return null;
    }
  },
};
