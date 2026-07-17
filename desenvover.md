# Plano de Desenvolvimento — Backend Laravel 13 para o Sistema de Reservas

Documento-guia (roteiro) para construir o backend em **Laravel 13**, integrá-lo ao frontend React já existente em `front/` (originalmente desenvolvido no Base44), preparar o deploy em hospedagem compartilhada da **Hostinger** e manter um ciclo de trabalho seguro: **implementar → testar → compilar frontend → commitar → só avançar quando os testes passarem → push só ao fim da fase**.

---

## 1. Visão Geral

- **Frontend:** React 18 + Vite + Tailwind + shadcn/ui (pasta `front/`). Usa o SDK do Base44 (`@base44/sdk`) para autenticação e para operações CRUD sobre as entidades `Campi`, `Grupo`, `Local`, `Reserva` e `User`.
- **Backend a ser criado:** Laravel 13, com API JSON. Autenticação via **Laravel Sanctum** (token pessoal salvo no `localStorage`, compatível com o comportamento atual do SDK). Todo o SDK do Base44 será substituído por um *wrapper* local (`front/src/api/base44Client.js`) que mantém a **mesma superfície** de chamadas — sem tocar em nenhuma página.
- **Banco de dados:**
  - Local (dev): **SQLite** (`database/database.sqlite`).
  - Produção (Hostinger): **MySQL**.
  - As migrações devem funcionar nos dois SGBDs (evitar tipos exclusivos de um; usar `enum` como `string` com constraint via validação de app, e usar `foreignId`/`nullable`/`cascadeOnDelete`).
- **Deploy:** git-based. Todo push é precedido de `npm run build` no frontend gerando artefatos dentro de `public/` do Laravel. A hospedagem aponta o Document Root para `public/` (ou usa `.htaccess` para reescrever).

---

## 2. Entidades e Contratos (extraídos do Base44)

| Entidade  | Campos (front)                                                                                                                                                                                                                                                                                            | Regras                                                                                            |
| --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `User`    | `id`, `email`, `full_name`, `role` (`admin`\|`user`)                                                                                                                                                                                                                                                       | `role` obrigatório. Papel padrão em registro público: `user`.                                     |
| `Campi`   | `nome*`, `sigla*`, `endereco`, `cidade`, `descricao`, `status` (`ativo`\|`inativo`)                                                                                                                                                                                                                        | `nome`+`sigla` obrigatórios.                                                                      |
| `Grupo`   | `nome*`, `campi_id*` (FK), `descricao`, `status`                                                                                                                                                                                                                                                          | Grupo pertence a um Campi.                                                                        |
| `Local`   | `nome*`, `campi_id*`, `grupo_id*`, `tipo*` (11 valores fixos), `capacidade`, `descricao`, `recursos`, `status`                                                                                                                                                                                             | `tipo` restrito à lista de `front/src/lib/tiposLocal.js`.                                        |
| `Reserva` | `titulo*`, `motivo*` (≥10 palavras), `campi_id`, `grupo_id`, `local_id*`, `tipo_local`, `data_inicial*`, `data_final*`, `horario_inicial*`, `horario_final*`, `responsavel_nome*`, `observacoes`, `status` (`confirmada`\|`pendente`\|`cancelada`), `recorrente` (bool), `created_date` (usado no detalhe) | Sem sobreposição no mesmo `local_id`. `data_final ≥ data_inicial`. `horario_final > horario_inicial`. |

**Superfície do SDK a preservar (para não alterar as páginas):**

```js
base44.auth.loginViaEmailPassword(email, password)
base44.auth.loginWithProvider('google', redirect)   // pode ser stub inicial
base44.auth.register({ email, password })
base44.auth.verifyOtp({ email, otpCode })
base44.auth.resendOtp(email)
base44.auth.resetPasswordRequest(email)
base44.auth.resetPassword({ resetToken, newPassword })
base44.auth.me()
base44.auth.isAuthenticated()
base44.auth.logout(redirect?)
base44.auth.setToken(token)
base44.auth.redirectToLogin(from)

base44.entities.<Campi|Grupo|Local|Reserva|User>.list()
base44.entities.<...>.create(data)
base44.entities.<...>.update(id, data)
base44.entities.<...>.delete(id)
base44.entities.<...>.bulkCreate(items)   // usado em reservas recorrentes
```

---

## 3. Estrutura Final do Repositório

```
agendas/
├── app/                     # Laravel
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── database.sqlite      # local (git-ignored)
├── public/                  # Document root; recebe o build do Vite
│   ├── index.html           # gerado por `npm run build`
│   ├── assets/              # gerado
│   └── index.php            # Laravel
├── routes/
│   ├── api.php
│   └── web.php              # fallback SPA
├── tests/
│   ├── Feature/
│   └── Unit/
├── front/                   # Frontend React (mantido)
│   ├── src/
│   │   └── api/base44Client.js  # SUBSTITUÍDO por wrapper axios → Laravel
│   └── vite.config.js       # `build.outDir` = '../public'
├── .env
├── composer.json
├── desenvover.md            # este arquivo
└── README.md
```

---

## 4. Regras Universais (valem para TODAS as fases)

Estas regras não são negociáveis. Cada fase termina obedecendo o mesmo ciclo:

1. **Implementar** somente o que a fase descreve.
2. **Escrever/atualizar testes automatizados** (PHPUnit ou Pest) que cubram:
   - Rotas, respostas HTTP, permissões (auth + role admin quando aplicável).
   - Regras de validação (formatos, obrigatoriedade, enums).
   - Regras de negócio específicas (conflito de reserva, hierarquia campi→grupo→local).
3. **Rodar a suíte completa**:
   ```bash
   php artisan test
   ```
   **Só avançar quando 100% verde.**
4. **Compilar o frontend** (mesmo que a fase não tenha mexido no React — garante que nada quebrou o build):
   ```bash
   cd front && npm run build && cd ..
   ```
5. **Commit** com mensagem no padrão convencional (`feat(auth): ...`, `test(reservas): ...`, `chore(build): recompila front`).
6. **Push só ao final da fase**, após:
   - Testes verdes,
   - Build gerado dentro de `public/`,
   - `git status` limpo.

**Não misturar fases num mesmo commit.** Cada fase (ver seções 6–14) tem seu próprio commit-de-conclusão marcado como `feat(fase-N): ...`.

**Nunca subir para produção sem `npm run build` recente.** Se o build estiver desatualizado, a Hostinger vai servir código velho.

---

## 5. Fase 0 — Preparação do Ambiente

### 5.1 Pré-requisitos locais

- PHP ≥ 8.3, Composer 2.x, Node ≥ 20, npm ≥ 10, Git.
- Extensões PHP: `pdo_sqlite`, `pdo_mysql`, `mbstring`, `openssl`, `xml`, `ctype`, `bcmath`, `curl`, `zip`.

### 5.2 Instalar o Laravel na raiz do projeto

O projeto já tem `.git/` e a pasta `front/`. Vamos instalar o Laravel **na raiz** (sem sobrescrever `front/`):

```bash
# Na raiz do repo (/home/francis/laravel/agendas)
composer create-project laravel/laravel:^13.0 tmp-laravel
# Mover conteúdo para a raiz, preservando front/ e desenvover.md
shopt -s dotglob
mv tmp-laravel/* tmp-laravel/.* ./ 2>/dev/null || true
rmdir tmp-laravel
```

Ajustar `.gitignore` para incluir os padrões do Laravel além do que já existe.

### 5.3 Instalar pacotes essenciais

```bash
composer require laravel/sanctum
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
php artisan install:api          # publica Sanctum + rota /api
./vendor/bin/pest --init         # se optar por Pest; senão manter PHPUnit
```

### 5.4 Configurar banco local (SQLite)

```bash
touch database/database.sqlite
```

`.env` (dev):

```
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
DB_DATABASE=/home/francis/laravel/agendas/database/database.sqlite
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
SESSION_DOMAIN=localhost
```

### 5.5 Configurar frontend para gerar no `public/` do Laravel

Editar `front/vite.config.js`:

```js
export default defineConfig({
  plugins: [react()],   // REMOVER o plugin do base44
  build: {
    outDir: '../public',
    emptyOutDir: false,        // não apagar index.php do Laravel
    assetsDir: 'assets',
  },
  server: { port: 5173 },
});
```

E `front/index.html` recebe o script já apontado para `/src/main.jsx` (mantém). Em dev usamos `npm run dev` (Vite em 5173) contra `php artisan serve` (8000).

### 5.6 Rota fallback do Laravel para servir a SPA

Em `routes/web.php`:

```php
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '^(?!api).*$');
```

### 5.7 Testes de sanidade

- `tests/Feature/HealthTest.php`: `GET /api/up` deve retornar 200.
- `php artisan test` verde.

### 5.8 Fechamento da Fase 0

- `cd front && npm install && npm run build`
- Commit: `chore(fase-0): bootstrap Laravel 13 + SQLite + build path`
- **Push**.

---

## 6. Fase 1 — Autenticação, Usuários e Seeder Admin

### 6.1 Migrações

- Ajustar `users` para incluir `full_name` (string, nullable), `role` (string, default `'user'`).
- Ativar tabela `personal_access_tokens` do Sanctum.
- Tabela `password_reset_tokens` já existe.
- Tabela `email_verifications`: `email`, `code (6 chars)`, `expires_at`. (para o fluxo OTP do Register)

### 6.2 Model `User`

- `HasApiTokens`, `Notifiable`.
- Cast `role` para string; helper `isAdmin(): bool`.
- Fillable inclui `full_name`, `role`.

### 6.3 Rotas em `routes/api.php`

```
POST   /auth/register           → envia código OTP para o e-mail (log em dev)
POST   /auth/verify-otp         → cria User com role=user e retorna { access_token }
POST   /auth/resend-otp
POST   /auth/login              → { access_token, user }
POST   /auth/logout             (auth:sanctum)
GET    /auth/me                 (auth:sanctum)
POST   /auth/forgot-password
POST   /auth/reset-password
```

Nota: `loginWithProvider('google')` fica como *stub* que retorna 501 — pode ser evoluído em fase futura sem quebrar o front.

### 6.4 Seeder Administrador

`database/seeders/AdminSeeder.php`:

```php
User::updateOrCreate(
    ['email' => env('ADMIN_EMAIL', 'admin@local.test')],
    [
        'full_name' => env('ADMIN_NAME', 'Administrador'),
        'password'  => Hash::make(env('ADMIN_PASSWORD', 'admin@123')),
        'role'      => 'admin',
        'email_verified_at' => now(),
    ]
);
```

Registrar em `DatabaseSeeder`. Rodar:

```bash
php artisan migrate:fresh --seed
```

### 6.5 Testes (obrigatórios antes de avançar)

- `Feature/Auth/RegisterOtpTest.php`
  - Registrar → cria registro em `email_verifications` (não cria User ainda).
  - Verify com código correto → cria User + retorna token.
  - Verify com código errado → 422.
  - OTP expirado → 422.
- `Feature/Auth/LoginTest.php`: credenciais válidas / inválidas.
- `Feature/Auth/MeTest.php`: `GET /auth/me` sem token → 401; com token → 200 + payload.
- `Feature/Auth/PasswordResetTest.php`: fluxo completo.
- `Feature/AdminSeederTest.php`: após seed, existe user com `role=admin` e senha do env.

### 6.6 Fechamento da Fase 1

- `php artisan test` verde.
- `cd front && npm run build`.
- Commit: `feat(fase-1): auth com Sanctum, OTP e seeder admin`.
- **Push**.

---

## 7. Fase 2 — CRUD de Campi

### 7.1 Migration `campi`

- `id`, `nome`, `sigla`, `endereco (nullable)`, `cidade (nullable)`, `descricao (nullable text)`, `status (default 'ativo')`, timestamps.

### 7.2 Model + FormRequest + Controller + Policy

- `CampiPolicy`: `viewAny`/`view` liberados (público lê no Home); `create/update/delete` só `admin`.
- Rotas `apiResource('campi', CampiController::class)` sob `Route::middleware('auth:sanctum')` **exceto** `index` e `show` que ficam também em rotas públicas (`/api/public/campi`).

### 7.3 Testes

- Cria/edita/deleta como admin: 200/201.
- Como usuário comum: 403 nas rotas de escrita.
- Sem auth: 401 nas privadas; 200 nas públicas.
- Validação: `nome` e `sigla` obrigatórios.

### 7.4 Fechamento

- Testes verdes → `npm run build` → commit `feat(fase-2): CRUD de campi` → **push**.

---

## 8. Fase 3 — CRUD de Grupos

### 8.1 Migration `grupos`

- `foreignId('campi_id')->constrained('campi')->cascadeOnDelete()`.
- Campos restantes conforme entidade.

### 8.2 Rotas / Model / Policy espelhando Fase 2

### 8.3 Testes

- Não permitir criar `grupo` com `campi_id` inexistente (422).
- Deletar `Campi` cascateia `Grupos` — validar no teste.
- Filtro `?campi_id=` no `index` (usado por `CampiDetail.jsx`).

### 8.4 Fechamento

- Testes verdes → `npm run build` → commit `feat(fase-3): CRUD de grupos` → **push**.

---

## 9. Fase 4 — CRUD de Locais

### 9.1 Migration `locais`

- FKs `campi_id`, `grupo_id`.
- `tipo` string; validação por rule `Rule::in(TIPOS_LOCAL)` — a lista fica em `config/tipos_local.php` espelhando `front/src/lib/tiposLocal.js`.
- `capacidade` integer nullable, `descricao`, `recursos`, `status`.

### 9.2 Regra de integridade

- No FormRequest de criação/atualização: `grupo_id` deve pertencer ao `campi_id`.

### 9.3 Testes

- Lista pública com filtros.
- Erros de validação (tipo inválido, grupo de outro campi).
- Permissões (admin escreve, user lê).

### 9.4 Fechamento

- Testes verdes → `npm run build` → commit `feat(fase-4): CRUD de locais` → **push**.

---

## 10. Fase 5 — Reservas (com validação de conflito)

### 10.1 Migration `reservas`

- `titulo`, `motivo (text)`, `campi_id`, `grupo_id`, `local_id`, `tipo_local`, `data_inicial`, `data_final`, `horario_inicial (time)`, `horario_final (time)`, `responsavel_nome`, `observacoes (text nullable)`, `status (default 'confirmada')`, `recorrente (bool default false)`, `user_id (FK users)`, timestamps.
- Índice composto `(local_id, data_inicial, data_final)`.

### 10.2 Regras de negócio (server-side, redundantes ao front)

1. `motivo` com no mínimo 10 palavras — validação custom.
2. `data_final ≥ data_inicial`.
3. `horario_final > horario_inicial`.
4. **Sem sobreposição** no mesmo `local_id`: reservas existentes com status ≠ `cancelada` no mesmo intervalo de datas e faixa horária impedem a criação/edição.
5. Usuário comum só vê **suas** reservas em `/minhas-reservas` (filtrar por `user_id`); admin vê tudo.

### 10.3 Endpoint `bulkCreate`

- `POST /api/reservas/bulk` recebe array; roda validação individual e de conflito em transação; se qualquer item falhar, rollback + 422 com detalhes por índice.

### 10.4 Endpoints públicos

- `GET /api/public/reservas` (leitura para a agenda), com filtros `?campi_id`, `?grupo_id`, `?local_id`, `?from`, `?to`.

### 10.5 Testes

- `Feature/Reservas/ConflitoTest.php`:
  - Duas reservas no mesmo local, mesmo dia, horários sobrepostos → segunda retorna 422.
  - Cancelada anterior **não** bloqueia.
  - Reservas em locais diferentes não conflitam.
- `Feature/Reservas/BulkCreateTest.php`:
  - 5 ocorrências, uma em conflito → nada é gravado, 422 com o índice do conflito.
- `Feature/Reservas/PermissionsTest.php`:
  - User comum só lista as próprias em `/api/reservas/minhas`.
  - Admin lista tudo em `/api/reservas`.
- Regra do motivo (< 10 palavras → 422).

### 10.6 Fechamento

- Testes verdes → `npm run build` → commit `feat(fase-5): reservas com validação de conflito e bulk` → **push**.

---

## 11. Fase 6 — Substituição do SDK Base44 no Frontend

Objetivo: manter **todas as páginas React** funcionando sem alterá-las, apenas trocando o cliente por um wrapper que fala com o Laravel.

### 11.1 Substituir `front/src/api/base44Client.js`

Criar cliente axios com:

- `baseURL: import.meta.env.VITE_API_URL || '/api'`.
- Interceptor de request injetando `Authorization: Bearer ${localStorage.getItem('base44_access_token')}` (mesmo storage key usado hoje).
- Interceptor de resposta que devolve `response.data`.

Exportar objeto `base44` com a **mesma superfície** listada na seção 2:

- `base44.auth.*` → chama os endpoints da Fase 1.
- `base44.entities.<Nome>.list()` → `GET /api/<recurso>`.
- `.create(data)` → `POST`.
- `.update(id, data)` → `PUT /api/<recurso>/{id}`.
- `.delete(id)` → `DELETE`.
- `.bulkCreate(arr)` → `POST /api/<recurso>/bulk`.

Mapeamento de nomes:

| Entidade front | Rota Laravel   |
| -------------- | -------------- |
| `Campi`        | `/api/campi`   |
| `Grupo`        | `/api/grupos`  |
| `Local`        | `/api/locais`  |
| `Reserva`      | `/api/reservas` |
| `User`         | `/api/users`   |

### 11.2 Ajustar `front/src/lib/app-params.js` e `AuthContext.jsx`

- Retirar as dependências do endpoint `/api/apps/public/prod/public-settings` — em vez disso, um `GET /api/public/settings` do Laravel retorna `{ auth: { google: false }, app_name: '...' }`.
- Manter o mesmo formato para não alterar `AuthContext`.
- Deletar `import { createAxiosClient } from '@base44/sdk/...'` e remover `@base44/sdk` do `package.json`.

### 11.3 Ajustar `front/vite.config.js`

- Remover `import base44 from '@base44/vite-plugin'` e a chamada `base44({...})`.
- Manter apenas `react()`.
- Adicionar proxy dev para `/api`:

```js
server: {
  port: 5173,
  proxy: { '/api': 'http://localhost:8000' },
}
```

### 11.4 Testes automatizados de front (mínimo viável)

Não é obrigatório montar Vitest completo, mas:

- Adicionar `npm run typecheck` e `npm run lint` no ciclo. **Ambos devem passar** antes do commit.

### 11.5 Testes de integração pelo backend

- Rodar a suíte `php artisan test` novamente (nada deveria quebrar; se quebrou é bug em serializer).

### 11.6 Fechamento

- `php artisan test` verde + `cd front && npm run lint && npm run typecheck && npm run build` sem erros.
- Testar manualmente no navegador: login com o admin do seeder, criar Campi/Grupo/Local/Reserva, ver Home/CampiDetail/MinhasReservas.
- Commit: `feat(fase-6): wrapper axios substitui SDK base44`.
- **Push**.

---

## 12. Fase 7 — Ajustes de Produção (Hostinger)

### 12.1 `.env` de produção

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://seu-dominio`.
- `DB_CONNECTION=mysql`, `DB_HOST=localhost`, `DB_DATABASE=<hostinger>`, `DB_USERNAME`, `DB_PASSWORD`.
- `SANCTUM_STATEFUL_DOMAINS=seu-dominio`.
- `SESSION_DRIVER=database` (Hostinger tem restrições em `file` sessions em alguns planos — validar).
- `MAIL_*` configurado para o SMTP da Hostinger.

### 12.2 Estrutura de deploy (git push → hospedagem)

Fluxo recomendado:

1. Local: garantir tudo verde (`php artisan test`, `npm run build`).
2. Commitar o `public/` gerado (opção mais simples em shared hosting, já que Hostinger nem sempre tem Node).
3. `git push origin main` para o remoto vinculado à Hostinger (via *Git deployment* do painel ou repo próprio + webhook).
4. No servidor, o Deploy Script roda:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

### 12.3 `.htaccess` na raiz do domínio

Se a Hostinger apontar o Document Root para `public_html` e o projeto estiver em outra pasta, criar `.htaccess` no `public_html` redirecionando para `public/`. Alternativa (mais limpa): configurar o Document Root direto para `<pasta-do-projeto>/public`.

### 12.4 Checklist antes de cada `git push` de produção

- [ ] `php artisan test` → todos verdes.
- [ ] `cd front && npm run lint && npm run typecheck && npm run build` → sem erros; `public/index.html` atualizado.
- [ ] `git status` limpo (nenhum arquivo não commitado).
- [ ] Migrações revisadas (nenhuma destrutiva sem plano).
- [ ] `.env` de produção **não commitado** (usar `.env.example`).
- [ ] Commit final da fase.
- [ ] `git push`.

### 12.5 Fechamento

- Commit: `chore(fase-7): configuração de deploy Hostinger`.
- **Push** para produção.

---

## 13. Convenções e Padrões

- **Commits**: convencionais (`feat`, `fix`, `test`, `chore`, `refactor`, `docs`), com escopo por fase quando fizer sentido: `feat(fase-3): ...`.
- **Branches**: trabalhar em `main` está OK para este projeto (single-dev). Se abrir feature branches, uma por fase, merge fast-forward.
- **PHP**: PSR-12, `declare(strict_types=1);` nos arquivos novos.
- **Nomenclatura**: rotas em plural (`/api/campi`, `/api/grupos`, `/api/locais`, `/api/reservas`); models em singular; Policies com sufixo `Policy`.
- **JSON de resposta**: usar `Resource` (Eloquent API Resource) para não vazar campos internos (`password`, `remember_token`).

---

## 14. Ciclo Padrão de UMA Fase (resumo executável)

```bash
# 1. Implementação
#    (código, migrações, seeders, testes escritos)

# 2. Testes automatizados
php artisan test
# Só continue se saída = "OK" / "PASS"

# 3. Frontend
cd front
npm run lint
npm run typecheck
npm run build
cd ..

# 4. Commit
git add -A
git commit -m "feat(fase-N): descrição curta"

# 5. Push (só ao FIM da fase)
git push origin main
```

Se `php artisan test` falhar em qualquer momento: **pare, corrija, rode de novo**. Não passe para a etapa 3 sem verde total.

---

## 15. Ordem Definitiva das Fases

| Fase | Objetivo                                | Commit final                                          |
| ---- | --------------------------------------- | ----------------------------------------------------- |
| 0    | Bootstrap Laravel + SQLite + build path | `chore(fase-0): bootstrap Laravel 13`                 |
| 1    | Auth Sanctum + OTP + seeder admin       | `feat(fase-1): auth com Sanctum, OTP e seeder admin`  |
| 2    | CRUD Campi                              | `feat(fase-2): CRUD de campi`                         |
| 3    | CRUD Grupos                             | `feat(fase-3): CRUD de grupos`                        |
| 4    | CRUD Locais                             | `feat(fase-4): CRUD de locais`                        |
| 5    | CRUD Reservas + conflito + bulk         | `feat(fase-5): reservas com validação de conflito`    |
| 6    | Substituir SDK base44 no front          | `feat(fase-6): wrapper axios substitui SDK base44`    |
| 7    | Deploy Hostinger (MySQL, cache, .env)   | `chore(fase-7): configuração de deploy Hostinger`     |

---

## 16. Pontos de Atenção

- **Não** manter dependências do `@base44/sdk` no `package.json` após a Fase 6 — o `npm run build` deve continuar funcionando sem elas.
- **CSRF vs Bearer token**: usamos Bearer + Sanctum em modo API. Não é necessário `SANCTUM_STATEFUL_DOMAINS` para o fluxo de token; só configurar se algum dia habilitar sessão com cookie.
- **Timezone**: `APP_TIMEZONE=America/Sao_Paulo` para as datas exibidas baterem com o `date-fns/locale/ptBR` do front.
- **CORS**: Laravel 11+ já vem com `config/cors.php`. Em produção, ambas as origens ficam no mesmo domínio, então CORS é irrelevante; em dev, o proxy do Vite resolve.
- **Migração destrutiva**: nunca rodar `migrate:fresh` em produção. Use apenas `migrate --force`.
- **Backup**: antes de qualquer `push` que altere schema em produção, exportar o dump MySQL pela Hostinger.

---

**Este documento é a fonte da verdade do plano.** Se algo mudar de rota (nova entidade, novo endpoint, mudança de deploy), atualize aqui antes de codar — e siga o mesmo ciclo: testes → build → commit → push.

---

# PARTE II — Evolução do Sistema (Fases 8+)

Esta parte complementa o plano original. Introduz aprovação de reservas, gerentes por Local, notificações por email, períodos (semestres), indisponibilidades de Local e cadastro de Recursos. **O ciclo padrão da seção 14 continua valendo** para todas as fases abaixo: implementar → testar → `npm run build` → commit → push.

## 17. Visão Geral das Novas Funcionalidades

| # | Funcionalidade | Fase |
| - | --- | ---- |
| 1 | Toggle "requer aprovação" por Local + fluxo pendente→confirmada/cancelada | Fase 8 |
| 2 | Envio de email em criação, aprovação e cancelamento (com motivo) | Fase 9 |
| 3 | Gerentes de Local (múltiplos por Local) — únicos que aprovam/cancelam | Fase 8 |
| 4 | Cadastro de Períodos (semestres) pelo admin + botão que preenche datas | Fase 10 |
| 5 | Cadastro de Recursos (som, copa, técnico…) com disponibilidade e agenda própria | Fase 12 |
| 6 | Indisponibilidades de Local (feriados, férias, dia da semana, faixa horária) | Fase 11 |

**Decisões arquiteturais confirmadas com o usuário:**
- Reserva nasce `pendente` **apenas se** o Local tem `requer_aprovacao=true`. Caso contrário, mantém o comportamento atual (`confirmada`).
- Somente gerentes daquele Local (e admin) podem aprovar ou cancelar. Cancelamento **sempre** exige `motivo_cancelamento`.
- Botão "Semestre" preenche `data_inicial` e `data_final` do formulário de reserva. O usuário continua escolhendo horário e (se recorrente) dias da semana.
- Emails via **SMTP da Hostinger** com fila **`database`** (`QUEUE_CONNECTION=database`). Em dev pode-se usar `MAIL_MAILER=log`.

---

## 18. Fase 8 — Gerentes de Local + Fluxo de Aprovação

### 18.1 Migrations

- `add_requer_aprovacao_to_locais_table`:
  - `locais.requer_aprovacao` — `boolean` default `false`.
- `create_local_gerentes_table` (pivot N:N):
  - `local_id` FK `locais.id` cascadeOnDelete.
  - `user_id` FK `users.id` cascadeOnDelete.
  - Primary composta `(local_id, user_id)`.
- `add_aprovacao_fields_to_reservas_table`:
  - `motivo_cancelamento` — `text nullable`.
  - `aprovada_por_id` — `foreignId nullable constrained('users')->nullOnDelete()`.
  - `aprovada_em` — `timestamp nullable`.
  - `cancelada_por_id` — `foreignId nullable constrained('users')->nullOnDelete()`.
  - `cancelada_em` — `timestamp nullable`.
- **Compatibilidade SQLite/MySQL**: usar `->change()` só quando necessário; preferir novas colunas nullable.

### 18.2 Models

- `Local`:
  - Adicionar `requer_aprovacao` em `#[Fillable]` e cast `boolean`.
  - Relação `gerentes(): BelongsToMany` sobre `local_gerentes`.
  - Método `temGerente(User $u): bool`.
- `User`:
  - Relação inversa `locaisGerenciados(): BelongsToMany`.
  - Helper `podeAprovarReserva(Reserva $r): bool { return $this->isAdmin() || $r->local->temGerente($this); }`.
- `Reserva`:
  - Adicionar campos novos ao `#[Fillable]`.
  - Casts: `aprovada_em`/`cancelada_em` → `datetime`.
  - Scope `scopePendentes` e `scopePorGerente(User $u)` para listar as reservas que aquele usuário pode aprovar.

### 18.3 Serviço/Controller

- Na criação (`ReservaController@store` e `@bulk`):
  ```php
  $local = Local::findOrFail($data['local_id']);
  $data['status'] = $local->requer_aprovacao ? 'pendente' : 'confirmada';
  ```
- Novos endpoints (sob `auth:sanctum`):
  ```
  PATCH  /api/reservas/{reserva}/aprovar   → 200 { reserva }
  PATCH  /api/reservas/{reserva}/cancelar  → 200 { reserva }   // body: { motivo_cancelamento: '...' }
  GET    /api/reservas/pendentes            → lista as reservas pendentes que o usuário pode aprovar (admin vê todas)
  ```
- Não permitir aprovar reserva já `cancelada`; não permitir cancelar já `cancelada`.
- **Regra de negócio**: cancelar exige `motivo_cancelamento` com no mínimo 5 palavras (custom rule `PalavrasMinimas`).

### 18.4 Policy

- `ReservaPolicy`:
  - `aprovar(User $u, Reserva $r)` → `$u->podeAprovarReserva($r) && $r->status === 'pendente'`.
  - `cancelar(User $u, Reserva $r)` → `$u->podeAprovarReserva($r) || $r->user_id === $u->id` (o próprio dono também pode cancelar a sua reserva).
- `LocalPolicy`:
  - `attachGerentes` → só admin.

### 18.5 Frontend

- `Admin.jsx` (`LocalForm`):
  - Toggle **"Requer aprovação"** ligado ao campo `requer_aprovacao`.
  - Multi-select de usuários gerentes (fetch em `base44.entities.User.list()`), gravado via novo endpoint `PUT /api/locais/{id}/gerentes` ou dentro do próprio `update` (payload `{ ..., gerentes: [id1, id2] }`).
- Nova aba **"Pendentes"** no `Admin.jsx` para gerentes / admin:
  - Lista `GET /api/reservas/pendentes`.
  - Botões **Aprovar** e **Cancelar** (Cancelar abre modal para preencher motivo).
- `MinhasReservas.jsx`: mostrar badge do status (`pendente/confirmada/cancelada`) — hoje já mostra status, apenas garantir que o campo `motivo_cancelamento` apareça quando presente.
- `base44Client.js`:
  ```js
  Reserva.aprovar = (id) => request("PATCH", `/reservas/${id}/aprovar`);
  Reserva.cancelar = (id, motivo_cancelamento) =>
    request("PATCH", `/reservas/${id}/cancelar`, { motivo_cancelamento });
  Reserva.pendentes = () => request("GET", "/reservas/pendentes");
  ```

### 18.6 Testes

- `Feature/Locais/GerentesTest.php`: attach/detach; só admin altera; `temGerente()` true/false.
- `Feature/Reservas/AprovacaoTest.php`:
  - Local com `requer_aprovacao=true` → nova reserva nasce `pendente`.
  - Local sem toggle → nova reserva nasce `confirmada`.
  - Gerente aprova → status vira `confirmada`, `aprovada_por_id` preenchido.
  - Não-gerente tenta aprovar → 403.
  - Aprovar reserva já `confirmada` → 422.
- `Feature/Reservas/CancelamentoTest.php`:
  - Sem `motivo_cancelamento` → 422.
  - Motivo com menos de 5 palavras → 422.
  - Dono da reserva pode cancelar a sua; outro usuário sem ser gerente → 403.
  - Reserva cancelada libera o slot (`conflitos()` já ignora status=`cancelada`, revalidar).

### 18.7 Fechamento da Fase 8

- Testes verdes → `cd front && npm run build` → commit `feat(fase-8): aprovação de reservas + gerentes de Local` → **push**.

---

## 19. Fase 9 — Notificações por Email

### 19.1 Configuração de fila e mail

- `.env` dev:
  ```
  MAIL_MAILER=log
  QUEUE_CONNECTION=database
  ```
- `.env.production.example`:
  ```
  MAIL_MAILER=smtp
  MAIL_HOST=smtp.hostinger.com
  MAIL_PORT=465
  MAIL_ENCRYPTION=ssl
  MAIL_USERNAME=agendas@seu-dominio
  MAIL_PASSWORD=...
  MAIL_FROM_ADDRESS=agendas@seu-dominio
  MAIL_FROM_NAME="Agendas UFVJM"
  QUEUE_CONNECTION=database
  ```
- Tabela `jobs` já existe (`0001_01_01_000002_create_jobs_table.php`) — verificar.
- Publicar/registrar worker: `php artisan queue:work --tries=3` (rodar via `supervisord`/cron a cada minuto na Hostinger: `php artisan queue:work --stop-when-empty`).

### 19.2 Mailables e Notifications

Preferir **Notifications** (`php artisan make:notification ReservaCriada` etc.) para poder enviar por múltiplos canais no futuro.

- `App\Notifications\ReservaCriada` (para dono, gerentes e responsáveis de recurso).
- `App\Notifications\ReservaAprovada` (para dono).
- `App\Notifications\ReservaCancelada` (para dono e gerentes) — recebe `$motivo`.

Todas devem implementar `ShouldQueue`.

### 19.3 Disparo

- Observer `App\Observers\ReservaObserver`:
  - `created`: notifica dono + gerentes do Local + (Fase 12) responsáveis dos Recursos escolhidos.
  - `updated`: se `status` mudou para `confirmada` via aprovação → notifica dono.
  - `updated`: se `status` mudou para `cancelada` → notifica dono e gerentes com o `motivo_cancelamento`.
- Registrar em `EventServiceProvider` (`Reserva::observe(ReservaObserver::class)`).

### 19.4 Frontend

- Modal **"Cancelar reserva"** com campo `motivo_cancelamento` (Textarea, min 5 palavras, contador live).
- Feedback UI: toast "Cancelamento notificado por email."
- Nenhum novo endpoint no `base44Client.js` além dos criados na Fase 8.

### 19.5 Testes

- `Feature/Notifications/ReservaCriadaTest.php`:
  - `Mail::fake()` / `Notification::fake()`.
  - Cria reserva → notifica dono + cada gerente do Local.
  - Local sem `requer_aprovacao` → mesma notificação (é criação, independe de aprovação).
- `Feature/Notifications/ReservaAprovadaTest.php`: só notifica quando `status` transita de `pendente` para `confirmada`.
- `Feature/Notifications/ReservaCanceladaTest.php`: dono e gerentes recebem; conteúdo contém o motivo.

### 19.6 Fechamento

- Testes verdes → `npm run build` → commit `feat(fase-9): notificações por email (criação, aprovação, cancelamento)` → **push**.

---

## 20. Fase 10 — Períodos (Semestres)

### 20.1 Migration `periodos`

- `id`, `nome` (ex.: "2026/1"), `data_inicio` (date), `data_fim` (date), `status` (`ativo`/`inativo`, default `ativo`), timestamps.
- Regra: `data_fim >= data_inicio` (validação).

### 20.2 Model, FormRequest, Controller, Policy

- `PeriodoPolicy`: `viewAny`/`view` público; `create/update/delete` só admin.
- Rotas:
  ```
  GET    /api/periodos                (público)
  POST   /api/periodos                (admin)
  PUT    /api/periodos/{periodo}      (admin)
  DELETE /api/periodos/{periodo}      (admin)
  ```

### 20.3 Frontend

- `Admin.jsx`: nova seção **"Períodos"** com CRUD (padrão do `AdminTable` já existente).
- `ReservationModal.jsx`: acima dos campos de data, faixa de **botões "Semestre"**:
  - Renderiza `periodos.filter(p => p.status === 'ativo')`.
  - Ao clicar, `setForm({ ..., data_inicial: p.data_inicio, data_fim: p.data_fim })`.
- `base44Client.js`: `entities.Periodo = makeEntity("periodos")`.

### 20.4 Testes

- `Feature/Periodos/CrudTest.php`: admin/usuário; validação de datas.
- `Feature/Periodos/PublicListTest.php`: sem token → 200.

### 20.5 Fechamento

- Testes verdes → `npm run build` → commit `feat(fase-10): períodos (semestres) e botão de pré-preenchimento` → **push**.

---

## 21. Fase 11 — Indisponibilidades de Local

Objetivo: permitir marcar que o Local **não pode ser reservado** em determinados momentos (feriado, férias, dia da semana fixo, faixa horária recorrente).

### 21.1 Migration `local_indisponibilidades`

- `id`.
- `local_id` FK cascadeOnDelete.
- `tipo` string — `data_especifica` | `periodo` | `recorrente_semanal`.
- `data_inicial` date nullable, `data_final` date nullable.
- `dias_semana` json nullable (array `[0..6]`, 0=Domingo).
- `horario_inicial` time nullable, `horario_final` time nullable (null significa "dia inteiro").
- `motivo` string nullable.
- timestamps.
- Índice `(local_id)`.

### 21.2 Regras

- `data_especifica`: usa `data_inicial` (single day). Se `horario_*` nulls → dia todo.
- `periodo`: usa `data_inicial` + `data_final`. Se `horario_*` nulls → dias todos.
- `recorrente_semanal`: usa `dias_semana` + `horario_*` (sem datas). Aplica-se indefinidamente.
- Regras validadas no FormRequest (`required_if` por tipo).

### 21.3 Ajuste em `Reserva::conflitos()`

Estender para também rejeitar reservas que sobreponham qualquer registro de `local_indisponibilidades`. Alternativa mais limpa: criar `LocalIndisponibilidade::conflita(...)` e chamar antes de criar/atualizar.

### 21.4 Endpoints

```
GET    /api/locais/{local}/indisponibilidades          (público, para o front bloquear na UI)
POST   /api/locais/{local}/indisponibilidades          (admin ou gerente do Local)
PUT    /api/indisponibilidades/{indisponibilidade}     (admin ou gerente)
DELETE /api/indisponibilidades/{indisponibilidade}     (admin ou gerente)
```

### 21.5 Frontend

- `Admin.jsx` → `LocalForm`: aba **"Indisponibilidades"** com sub-CRUD (usar tabs do shadcn/ui).
- `Agenda.jsx`: pintar dias/horas indisponíveis em cinza + tooltip com `motivo`.
- `base44Client.js`: `entities.LocalIndisponibilidade = makeEntity("indisponibilidades")` e helper `Local.indisponibilidades(id)`.

### 21.6 Testes

- `Feature/Locais/IndisponibilidadeCrudTest.php`.
- `Feature/Reservas/ConflitoComIndisponibilidadeTest.php`:
  - Feriado (data_especifica) → reserva no dia falha 422.
  - Recorrente domingo → reserva de domingo falha 422; segunda passa.
  - Faixa horária 22h-6h → reserva 23h-1h falha; 14h-16h passa.

### 21.7 Fechamento

- Testes verdes → `npm run build` → commit `feat(fase-11): indisponibilidades de Local (feriados, faixas, dias)` → **push**.

---

## 22. Fase 12 — Cadastro de Recursos + vínculo com Reservas

### 22.1 Migrations

- `create_recursos_table`:
  - `id`.
  - `nome` string (ex.: "Som", "Copa", "Técnico de Lab").
  - `responsavel_nome` string.
  - `responsavel_email` string.
  - `quantidade` unsignedInteger default 1.
  - `status` string default `ativo`.
  - timestamps.
- `create_recurso_disponibilidades_table` (mesmo formato de `local_indisponibilidades` porém no sentido oposto — descreve **quando o recurso ESTÁ disponível**):
  - `id`.
  - `recurso_id` FK cascadeOnDelete.
  - `dias_semana` json (ex.: `[1,2,3,4,5]` = seg-sex).
  - `horario_inicial` time, `horario_final` time.
  - Um recurso pode ter várias linhas (ex.: 08:00-12:00 e 14:00-18:00, seg-sex; 10:00-13:00 no domingo).
- `create_reserva_recurso_table` (pivot):
  - `reserva_id`, `recurso_id`, `quantidade` unsignedInteger default 1.
  - Primary composta `(reserva_id, recurso_id)`.

### 22.2 Models

- `Recurso` com relação `disponibilidades()` (HasMany), `reservas()` (BelongsToMany pivot com `quantidade`).
- `RecursoDisponibilidade`.
- `Reserva.recursos(): BelongsToMany` com `withPivot('quantidade')`.

### 22.3 Regra de disponibilidade

Ao criar/atualizar reserva com recursos:

1. Para cada `recurso_id + quantidade` do payload:
   - Verificar se **existe pelo menos uma janela** em `recurso_disponibilidades` que cobre o(s) dia(s) da semana das datas da reserva **e** contém `[horario_inicial, horario_final]`.
   - Se não cobre → 422 `"Recurso X não disponível neste horário."`.
2. Verificar **soma da quantidade** já reservada no mesmo intervalo:
   - `SUM(reserva_recurso.quantidade)` das reservas ativas (status ≠ cancelada) que se sobrepõem em data+hora.
   - Se `soma + quantidade_pedida > recurso.quantidade` → 422 `"Recurso X esgotado neste horário."`.

Encapsular em `RecursoDisponibilidadeService::verificar(int $recursoId, int $qtd, string $di, string $df, string $hi, string $hf, ?int $ignorarReservaId = null)`.

### 22.4 Endpoints

```
GET    /api/recursos                    (auth: qualquer usuário logado)
POST   /api/recursos                    (admin)
PUT    /api/recursos/{recurso}          (admin)
DELETE /api/recursos/{recurso}          (admin)
GET    /api/recursos/{recurso}/agenda   (só responsável — email match — ou admin)
POST   /api/recursos/{recurso}/verificar-disponibilidade   (usado pela UI ao escolher recurso na reserva)
```

Extensão em `POST /api/reservas` e `POST /api/reservas/bulk`: aceitar `recursos: [{ id, quantidade }]`.

### 22.5 Notificações (integra Fase 9)

- Ao criar reserva com recursos → `ReservaCriada` também dispara para cada `responsavel_email` dos recursos escolhidos.
- Ao aprovar/cancelar reserva → também notifica os responsáveis dos recursos vinculados.
- Adicionar rota `mail(to: [$dono, ...$gerentes, ...$responsaveisRecursos])` no observer.

### 22.6 Agenda do Responsável

- Endpoint `/api/recursos/{id}/agenda` retorna reservas ativas do recurso (com dono, local, datas, horários).
- **Auth**: só admin ou usuário cujo `email === recurso.responsavel_email`. Como o responsável pode não ter conta, fornecer alternativa: **link mágico** enviado por email trimestralmente **fora do escopo desta fase**; por ora, exigir conta e match de email.

### 22.7 Frontend

- `Admin.jsx`: nova seção **"Recursos"** com CRUD + sub-CRUD de disponibilidades (grade de dias × faixas horárias, similar ao `RecurringDaysPicker`).
- `ReservationModal.jsx`: bloco **"Recursos"** com multi-seleção (checkbox + input de quantidade). Antes de enviar, chamar `verificar-disponibilidade` para feedback rápido.
- Nova página `AgendaRecurso.jsx` (rota `/recursos/:id/agenda`) para o responsável.
- `base44Client.js`:
  ```js
  entities.Recurso = makeEntity("recursos");
  entities.Recurso.disponibilidade = (id, payload) =>
    request("POST", `/recursos/${id}/verificar-disponibilidade`, payload);
  entities.Recurso.agenda = (id) => request("GET", `/recursos/${id}/agenda`);
  ```

### 22.8 Testes

- `Feature/Recursos/CrudTest.php`.
- `Feature/Recursos/DisponibilidadeTest.php`:
  - Fora da janela semanal → falha.
  - Dentro da janela mas quantidade esgotada → falha.
  - Cabe → sucesso.
- `Feature/Recursos/AgendaTest.php`:
  - Responsável (email match) vê → 200.
  - Outro usuário → 403.
- `Feature/Reservas/ReservaComRecursoTest.php`:
  - Bulk com um recurso indisponível → rollback completo.

### 22.9 Fechamento

- Testes verdes → `npm run build` → commit `feat(fase-12): cadastro de recursos e vínculo com reservas` → **push**.

---

## 23. Impacto no Wrapper `base44Client.js`

Resumo das novas entidades/métodos a serem expostos (mantendo o mesmo estilo):

```js
entities.Periodo = makeEntity("periodos");
entities.Recurso = makeEntity("recursos");
entities.LocalIndisponibilidade = makeEntity("indisponibilidades");

Reserva.aprovar   = (id) => request("PATCH", `/reservas/${id}/aprovar`);
Reserva.cancelar  = (id, motivo_cancelamento) => request("PATCH", `/reservas/${id}/cancelar`, { motivo_cancelamento });
Reserva.pendentes = () => request("GET", "/reservas/pendentes");

Local.gerentes            = (id) => request("GET",  `/locais/${id}/gerentes`);
Local.setGerentes         = (id, userIds) => request("PUT", `/locais/${id}/gerentes`, { user_ids: userIds });
Local.indisponibilidades  = (id) => request("GET", `/locais/${id}/indisponibilidades`);

Recurso.disponibilidade = (id, payload) => request("POST", `/recursos/${id}/verificar-disponibilidade`, payload);
Recurso.agenda          = (id) => request("GET", `/recursos/${id}/agenda`);
```

---

## 24. Ordem Definitiva (Fases 8–12)

| Fase | Objetivo                                                            | Commit final                                                       |
| ---- | ------------------------------------------------------------------- | ------------------------------------------------------------------ |
| 8    | Aprovação de reservas + gerentes de Local                           | `feat(fase-8): aprovação de reservas + gerentes de Local`          |
| 9    | Notificações por email (criação, aprovação, cancelamento com motivo) | `feat(fase-9): notificações por email`                            |
| 10   | Períodos (semestres) + botão de pré-preenchimento                    | `feat(fase-10): períodos (semestres)`                              |
| 11   | Indisponibilidades de Local                                          | `feat(fase-11): indisponibilidades de Local`                       |
| 12   | Recursos + vínculo com reservas + agenda do responsável              | `feat(fase-12): cadastro de recursos e vínculo com reservas`       |

Fases 8 e 9 têm dependência forte (email de aprovação/cancelamento pressupõe o fluxo da fase 8). Fase 12 depende da fase 9 para notificar responsáveis dos recursos. Fases 10 e 11 são independentes entre si e podem ser trocadas de ordem se conveniente.

---

## 25. Novos Pontos de Atenção

- **Fila (`queue:work`) na Hostinger**: shared hosting não roda daemon; alternativa é agendar `* * * * * php artisan queue:work --stop-when-empty --max-time=50` no cron do painel. Documentar no `deploy.sh`.
- **`MAIL_FROM_ADDRESS`** precisa ser um endereço criado no painel da Hostinger, senão os emails caem em spam. Preferir DKIM/SPF já configurados.
- **Dono da reserva pode cancelar a própria** (fase 8) — mas quem aprova é sempre gerente/admin.
- **Recursos vs Locais**: recurso NÃO é local — não bloqueia a agenda do Local, apenas verifica se o recurso escolhido está livre. Um mesmo dia/horário no mesmo Local só pode ter uma reserva; um mesmo dia/horário pode consumir várias unidades do mesmo recurso, desde que respeite `recurso.quantidade`.
- **Responsável de Recurso sem conta**: cobrir em fase futura (link mágico ou registro implícito). Por ora, exigir que o email do responsável corresponda a um `users.email`.
- **Retrocompatibilidade**: reservas criadas antes da fase 8 ficam com `status='confirmada'`, `aprovada_por_id=null`, sem `motivo_cancelamento` — comportamento aceitável (não retroativo).

---

## 26. Fase 13 — Unidades (Patrimônio), Filtro Real de Recursos, Remoção Assistida e Relatório

Depois da Fase 12 o cadastro de recurso funciona como um **balde** com `quantidade` inteira. A Fase 13 quebra esse balde em **unidades individuais** com código de patrimônio, torna o modal de reserva ciente da disponibilidade real, permite ao admin **remover uma unidade quebrada** com fluxo de revisão antes de enviar avisos, e adiciona um **relatório de uso** com três recortes + export CSV.

### 26.1 Modelo de dados

Nova tabela `recurso_unidades`:

```
id
recurso_id (FK -> recursos, cascadeOnDelete)
patrimonio (string, 60) — livre, único por recurso_id
status (enum: 'ativo' | 'inativo') — default 'ativo'
observacoes (text, nullable) — motivo da inativação, etc.
timestamps
UNIQUE (recurso_id, patrimonio)
```

- A coluna `recursos.quantidade` **passa a ser derivada**: `unidades()->where('status','ativo')->count()`. Removê-la do formulário e do payload de criação/atualização; manter a coluna no banco por retrocompatibilidade dos seeds antigos (populada por observer que sincroniza a cada change de unidade), mas o backend nunca mais lê dessa coluna para lógica.
- Migração de dados (para quem já rodou a Fase 12): para cada recurso existente, criar N unidades com patrimônio `AUTO-{recurso_id}-{n}` — assim o schema fica coerente sem apagar nada.

### 26.2 Filtro real de recursos no modal (Fase 13.2)

Novo endpoint:

```
POST /api/recursos/disponiveis
Body: {
  local_id?: number,        // opcional (não bloqueia hoje, mas útil futuramente)
  ocorrencias: [             // 1 item para reserva única; N para recorrente
    { data_inicial, data_final, horario_inicial, horario_final }
  ]
}
Resposta: [
  { id, nome, saldo_minimo }   // saldo_minimo = menor saldo entre ocorrências
]
```

- Backend itera pelos recursos ativos, chama `RecursoDisponibilidadeService::saldoNaJanela()` (novo método que retorna `int` em vez de `bool`), computa mínimo, e só inclui se `> 0`.
- Modal chama esse endpoint sempre que `local_id + datas + horários` (ou o array de ocorrências, para recorrente) muda, com debounce curto.
- Se `saldo_minimo > 0`: mostra "até N disponíveis" (N = saldo). Se lista vazia: seção "Recursos adicionais" some.
- Ajustar o alerta verde: só diz "todas as ocorrências disponíveis" se o local **e** os recursos selecionados estão ok.

### 26.3 CRUD de Unidades (Fase 13.1)

Sub-rotas do recurso:

```
GET    /api/recursos/{recurso}/unidades
POST   /api/recursos/{recurso}/unidades       { patrimonio, observacoes? }
PATCH  /api/recursos/{recurso}/unidades/{id}  { patrimonio?, status?, observacoes? }
```

- Store: valida `patrimonio` único por `recurso_id`; cria com `status='ativo'`.
- Update: pode reativar (`ativo`) ou apenas renomear patrimônio.
- Não expõe `DELETE` — remoção real é feita pela rota da 13.4 (que dispara notificações).
- UI: no formulário de recurso do Admin, uma nova seção "Unidades / Patrimônios" logo abaixo das disponibilidades, com input livre + botão adicionar + tabela.

### 26.4 Remoção de unidade com revisão (Fase 13.3)

Duas rotas em par:

```
POST /api/recursos/{recurso}/unidades/{id}/preview-remocao
    Resposta: {
      afetados: [
        { reserva_id, titulo, data, horario, usuario, motivo_selecao: 'sorteio' }
      ],
      resumo: { slots_conflitantes: 3, reservas_afetadas: 3 }
    }

POST /api/recursos/{recurso}/unidades/{id}/confirmar-remocao
    Body: { reserva_ids_desvincular: number[] }   // admin pode ajustar
    Resposta: { unidade: {...}, desvinculadas: N }
```

- **preview-remocao**: sistema calcula, para cada slot (dia+horário) em que a soma de `quantidade` já reservada estava saturando a quantidade atual, quantas reservas precisam perder o recurso. Sorteio determinístico (seed = unidade_id) por reproducibilidade em preview vs confirm.
- **confirmar-remocao**: dentro de uma transação:
  1. Detach das reservas escolhidas em `reserva_recurso` (mantém `Reserva`, remove só o vínculo).
  2. `RecursoUnidade->update(['status'=>'inativo', 'observacoes'=>...])`.
  3. `Notification::send($usuarios, new ReservaRecursoRemovido($reserva, $recurso, $motivo))`.
- A reserva do **local continua ativa** — o cancelamento é do vínculo, não da reserva.

Nova notificação `ReservaRecursoRemovido` (ShouldQueue, MailMessage): "Sua reserva `X` no dia `dd/mm` teve o recurso `Som` removido porque uma unidade foi inativada. A reserva do local continua ativa."

UI: na tabela de unidades, botão "Remover" abre modal que chama preview, mostra a lista de reservas afetadas com checkbox marcado, admin pode desmarcar/ajustar, botão "Confirmar remoção" chama confirmar-remocao.

### 26.5 Relatório de uso (Fase 13.4)

Três endpoints, todos aceitam `data_inicial` e `data_final` (defaults: hoje → +90 dias):

```
GET /api/recursos/{recurso}/relatorio/reservas?data_inicial&data_final&format=json|csv
    → tabela: reserva_id, titulo, data, horario, local, usuario, quantidade

GET /api/recursos/{recurso}/relatorio/ocupacao?data_inicial&data_final&format=json|csv
    → por mês:  horas_reservadas, horas_disponiveis, ocupacao_pct

GET /api/recursos/{recurso}/relatorio/unidades?data_inicial&data_final&format=json|csv
    → por unidade: patrimonio, horas_alocadas_estimadas, status
```

- `format=csv`: retorna `text/csv` com headers apropriados, aproveitando as mesmas queries.
- Ocupação: `horas_disponiveis` calculadas pelas disponibilidades (janelas seg-sex 08-12, etc.) × dias no período. `horas_reservadas` = soma das durações das reservas × quantidade reservada. Ocupação = razão × 100.
- **Uso por unidade** é estimado, não real: as reservas se ligam ao recurso (não a uma unidade específica). O relatório distribui as horas reservadas do slot entre as unidades ativas proporcionalmente. Documentar essa limitação no cabeçalho da tabela.

UI: nova aba "Relatórios" no Admin ao lado de "Recursos", com dropdown de recurso, dropdown de período, três abas (Reservas / Ocupação / Unidades) e botão CSV.

### 26.6 Impacto no wrapper `base44Client.js`

```js
Recurso.disponiveis         = (payload) => request("POST", "/recursos/disponiveis", payload);
Recurso.unidades            = (id) => request("GET", `/recursos/${id}/unidades`);
Recurso.criarUnidade        = (id, body) => request("POST", `/recursos/${id}/unidades`, body);
Recurso.atualizarUnidade    = (id, uid, body) => request("PATCH", `/recursos/${id}/unidades/${uid}`, body);
Recurso.previewRemocao      = (id, uid) => request("POST", `/recursos/${id}/unidades/${uid}/preview-remocao`);
Recurso.confirmarRemocao    = (id, uid, body) => request("POST", `/recursos/${id}/unidades/${uid}/confirmar-remocao`, body);
Recurso.relatorioReservas   = (id, params) => request("GET", `/recursos/${id}/relatorio/reservas?${qs(params)}`);
Recurso.relatorioOcupacao   = (id, params) => request("GET", `/recursos/${id}/relatorio/ocupacao?${qs(params)}`);
Recurso.relatorioUnidades   = (id, params) => request("GET", `/recursos/${id}/relatorio/unidades?${qs(params)}`);
```

### 26.7 Ordem de execução

| Sub-fase | Objetivo                                          | Commit                                                                                    |
| -------- | ------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| 13.1     | Unidades + patrimônio + quantidade derivada       | `feat(fase-13.1): unidades de recurso com patrimônio`                                     |
| 13.2     | Filtro real no modal de reserva                    | `feat(fase-13.2): filtra recursos por disponibilidade real`                               |
| 13.3     | Remover unidade com preview + revisão + notif.     | `feat(fase-13.3): remoção assistida de unidade com desvinculação de reservas`             |
| 13.4     | Relatório (reservas, ocupação, unidades) + CSV     | `feat(fase-13.4): relatório de uso de recursos com export CSV`                            |

Cada sub-fase é auto-contida e testável. 13.2/13.3/13.4 dependem apenas de 13.1.

### 26.8 Pontos de atenção específicos

- **Coluna `recursos.quantidade`**: manter no banco, mas fazer o Model computar `getQuantidadeAttribute()` a partir de `unidades_ativas_count`. Assim JSON continua expondo `quantidade` sem quebrar consumidores. Observer opcional para sincronizar coluna física.
- **Migração de dados dos seeds antigos**: rodar num `RecursoUnidadeBackfillSeeder` que só executa se `recurso_unidades` estiver vazio para recursos existentes; determinístico (patrimônio `AUTO-{id}-{n}`).
- **Preview vs Confirm**: sortear com seed determinística garante que o admin veja o mesmo sorteio se apenas clicar "recomputar"; a lista final vem no body do confirm, então mesmo se o admin editar, o backend usa o que ele mandou.
- **CSV**: usar streamed response (`response()->streamDownload`) para evitar problemas de memória se o período for grande.
- **Ocupação pode passar de 100%**: se o admin reduziu quantidade e ainda não desvinculou, o denominador cai. O relatório deve exibir "sobrealocado" nesse caso.
