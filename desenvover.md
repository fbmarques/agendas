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
