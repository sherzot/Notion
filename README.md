## Notion Mini — Laravel 12 + Next.js (PWA) + PostgreSQL + Telegram

### Nima qiladi?
- **Tasks / Notes / Calendar events** yaratish (REST API)
- Har bir action **Telegram bot** orqali xabar bo‘lib keladi
- **Calendar reminders**: event yaqinlashganda avtomatik Telegram eslatma (Queue + Scheduler)
- **Event logs**: hamma action `event_logs` jadvalida saqlanadi, dashboard’da ko‘rinadi (filter + search bilan)

### Stack
- **Backend**: Laravel 12 (Sanctum auth, Queue, Scheduler)
- **Frontend**: Next.js (App Router) + PWA-ready UI
- **DB**: PostgreSQL
- **Notifications**: Telegram Bot API (`sendMessage`)
- **Infra**: Docker Compose + GitHub Actions (Docker Hub push)

### Arxitektura (qisqa)
- `POST /api/tasks`, `POST /api/notes`, `POST /api/calendar-events` → yaratadi → `event_logs`ga yozadi → Queue job Telegramga yuboradi
- `POST /api/telegram/test` → mavjud telegram target’larga test xabar
- `GET /api/event-logs` → dashboard uchun event loglar (filter/search)
- Scheduler: `notion:send-reminders` → due bo‘lgan event’lar uchun `calendar_event.reminder` log yaratadi → Telegramga ketadi

---

## Lokal ishga tushirish (Docker)

### 1) Environment
Root papkada quyidagilar kerak:
- **`APP_KEY`**: Laravel app key
- **`TELEGRAM_BOT_TOKEN`**: Telegram bot token
- (ixtiyoriy) **`NEXT_PUBLIC_API_BASE_URL`**: lokalda default `http://localhost:8000`

`APP_KEY` olish:

```bash
cd backend && php artisan key:generate --show
```

Masalan:

```bash
export APP_KEY="base64:...."
export TELEGRAM_BOT_TOKEN="123456:ABC..."
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
1) Bot’ga Telegram’da `/start` yozing
2) Dashboard → **Telegram** bo‘limida `chat_id`ni saqlang
3) **Send test** bosing (agar `ok: true` bo‘lsa hammasi to‘g‘ri)

Eslatma:
- `chat not found` → `chat_id` noto‘g‘ri
- `bots can't send messages to bots` → bot ID yozib qo‘yilgan

---

## Reminders (Calendar)
Scheduler container `php artisan schedule:work` bilan ishlaydi.
Command:

```bash
docker compose exec backend php artisan notion:send-reminders
```

Event due bo‘lsa `calendar_event.reminder` telegramga ketadi.

---

## Event logs API
- `GET /api/event-logs`
  - Query:
    - `kind=task|note|calendar|reminder`
    - `q=<search>` (type/payload bo‘yicha)

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
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
```



