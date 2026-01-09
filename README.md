## Notion (Laravel + Next.js) — Docker + GitHub Actions

### Lokal ishga tushirish (Docker)

Avval `APP_KEY` yarating:

```bash
cd backend && php artisan key:generate --show
```

So‘ng uni env qilib qo‘ying (misol):

```bash
export APP_KEY="base64:...."
```

```bash
docker compose up --build
```

Keyin backend migratsiya:

```bash
docker compose exec backend php artisan migrate
```

- Backend: `http://localhost:8000`
- Frontend: `http://localhost:3000`

### Docker Hub publish (GitHub Actions)

Repo `main` branch’iga push bo‘lganda workflow ikki image’ni push qiladi:
- `sherdev/notion-backend:latest`
- `sherdev/notion-frontend:latest`

GitHub repo Secrets qo‘shing:
- `DOCKERHUB_USERNAME`
- `DOCKERHUB_TOKEN`

