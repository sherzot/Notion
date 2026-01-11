## Notion Mini — AI-assisted productivity (Laravel 12 + Next.js + PostgreSQL + Telegram)

### Nima qiladi?
- **Tasks / Notes / Calendar events** (CRUD) + **Event logs**
- **Telegram**: create/reminder/digest xabarlar (targets orqali)
- **AI (OpenAI GPT‑4o)**:
  - note’dan **task extraction**
  - **title/tags suggestion**
  - **natural command → action plan → execute** (dashboard + telegram bot)
  - **tone classifier**
  - **weekly digest (coach mode)** *(on-demand)*

### Stack
- **Backend**: Laravel 12 (Sanctum auth, Queue, Scheduler)
- **Frontend**: Next.js (App Router) + dashboard UI + `/api` reverse-proxy
- **DB**: PostgreSQL
- **Notifications**: Telegram Bot API (`sendMessage`)
- **AI**: OpenAI Chat Completions (`gpt-4o`, JSON-only)
- **Infra**: Docker Compose (+ dev/prod targets) + GitHub Actions (Docker Hub push)

### Arxitektura (qisqa)
- `POST /api/tasks`, `POST /api/notes`, `POST /api/calendar-events` → yaratadi → `event_logs`ga yozadi → Queue job Telegramga yuboradi
- `POST /api/telegram/test` → mavjud telegram target’larga test xabar
- `GET /api/event-logs` → dashboard uchun event loglar (filter/search)
- Scheduler: `notion:send-reminders` → due bo‘lgan event’lar uchun `calendar_event.reminder` log yaratadi → Telegramga ketadi
- AI endpoints (`/api/ai/*`) → OpenAI’ga so‘rov → JSON qaytaradi
- Telegram inbound (`POST /api/telegram/webhook`) → `chat_id` orqali user topadi → AI parse-command → task/event/note yaratadi → botga javob qaytaradi

---

## Lokal ishga tushirish (Docker, DEV)

### 1) Environment
Root papkada `.env` (yoki shell env) orqali quyidagilar kerak:
- **`APP_KEY`**: Laravel app key (majburiy)
- **`TELEGRAM_BOT_TOKEN`**: Telegram bot token (majburiy, telegram features uchun)
- **`OPENAI_API_KEY`**: OpenAI API key (majburiy, AI features uchun)
- (ixtiyoriy) **`OPENAI_MODEL`**: default `gpt-4o`
- (ixtiyoriy) **`OPENAI_BASE_URL`**: default `https://api.openai.com/v1/chat/completions`
- (ixtiyoriy) **`FRONTEND_URL`**: default `http://localhost:3000`
- (ixtiyoriy) **`NEXT_PUBLIC_API_BASE_URL`**: default `http://localhost:8000`

`APP_KEY` olish:

```bash
cd backend && php artisan key:generate --show
```

Masalan:

```bash
export APP_KEY="base64:...."
export TELEGRAM_BOT_TOKEN="123456:ABC..."
export OPENAI_API_KEY="sk-..."
```

### 2) Compose up + migrate

```bash
cd /Users/sher_developer/Desktop/Notion
docker compose up -d --build
docker compose exec backend php artisan migrate --force
```

### URL’lar
- **Frontend**: `http://localhost:3000`
- **Backend**: `http://localhost:8000/api/health`
- **DB UI (Adminer)**: `http://localhost:8081`
  - System: PostgreSQL
  - Server: `db`
  - Username: `notion`
  - Password: `notion`
  - Database: `notion`

### Telefon + Desktop bir xil ishlashi
Frontend backend’ga **`/api` proxy** qiladi. Shuning uchun:
- Desktop: `http://localhost:3000`
- Telefon: `http://<laptop-ip>:3000`

---

## Telegram setup (chat_id)
1) Bot’ga Telegram’da `/start` yozing → bot **chat_id** ni ko‘rsatadi
2) Dashboard → **Telegram** bo‘limida `type=private` va `chat_id` ni saqlang (**Save target**)
3) Dashboard → **Send test** bosing (Telegram API `ok: true` bo‘lsa hammasi to‘g‘ri)

Eslatma:
- `chat not found` → `chat_id` noto‘g‘ri
- `bots can't send messages to bots` → bot ID yozib qo‘yilgan
- Telegram target bo‘lmasa **hech qachon Telegramga xabar ketmaydi** (DB’da `telegram_targets` 0 bo‘ladi)

---

## Reminders (Calendar)
Scheduler container `php artisan schedule:work` bilan ishlaydi.
Command:

```bash
docker compose exec backend php artisan notion:send-reminders
```

Event due bo‘lsa `calendar_event.reminder` telegramga ketadi.

---

## AI (Dashboard + API)
Dashboard’da:
- **AI Suggest title/tags** (Note body → title/tags)
- **AI Extract** (text → task list)
- **AI Natural command** (text → plan preview → execute)

API endpoints (auth:sanctum):
- `POST /api/ai/title-tags` `{ "text": "..." }`
- `POST /api/ai/extract-tasks` `{ "text": "..." }`
- `POST /api/ai/parse-command` `{ "text": "..." }`
- `POST /api/ai/tone` `{ "text": "..." }`
- `POST /api/ai/weekly-digest` `{}`

OpenAI rate-limit bo‘lsa `429` va JSON error qaytadi (frontend Status’da request_id ko‘rinadi).

---

## Event logs API
- `GET /api/event-logs`
  - Query:
    - `kind=task|note|calendar|reminder`
    - `q=<search>` (type/payload bo‘yicha)

---

## Telegram inbound commands (webhook)
Webhook endpoint:
- `POST /api/telegram/webhook`

Botga yozilgan text AI orqali parse qilinadi va quyidagilar yaratiladi:
- Task → `/api/tasks`
- Calendar event → `/api/calendar-events`
- Note → `/api/notes`

Xavfsizlik:
- Webhook faqat create action’larni execute qiladi (delete/update/search yo‘q).
- Origin chat’ga “double message” bo‘lmasligi uchun event-log notifier origin chat’ni skip qiladi.

---

## Docker Hub publish (GitHub Actions)
Repo `main` branch’iga push bo‘lganda GitHub Actions image push qiladi:
- `sherdev/notion-backend:latest`
- `sherdev/notion-frontend:latest`

GitHub repo secrets:
- `DOCKERHUB_USERNAME`
- `DOCKERHUB_TOKEN` (yoki eski nom bilan `SHERDEV`)

---

## Docker Hub image’lardan ishga tushirish (prod-like)
Docker Hub’ga push bo‘lgan image’lar bilan run qilish:

```bash
cd /Users/sher_developer/Desktop/Notion
export APP_KEY="base64:...."
export TELEGRAM_BOT_TOKEN="123456:ABC..."
export OPENAI_API_KEY="sk-..."
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
```

---

## Testing
Local dev docker image’da testlar ishlaydi:

```bash
docker compose exec backend php artisan test
```

---

## Security (xavfsizlik)
- **Hech qachon** real `OPENAI_API_KEY`/`TELEGRAM_BOT_TOKEN`/`APP_KEY` ni repo’ga commit qilmang.
- `.env.example` faqat placeholder (`REPLACE_ME`) bo‘lishi kerak.
- Loglarda tokenlar chiqmasin (kalitlarni print qilmaymiz).
- AI rate-limit (`429`) normal holat: billing/limits tekshiring yoki key/plan yangilang.


