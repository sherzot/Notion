## Notion Mini Backend (Laravel 12)

Bu katalog backend (Laravel 12) uchun. Loyiha bo‘yicha to‘liq setup va foydalanish:
- Root `README.md`

### Tez buyruqlar (Docker)
- **Migrate**:

```bash
docker compose exec backend php artisan migrate --force
```

- **Test**:

```bash
docker compose exec backend php artisan test
```

### Xavfsizlik
Kalitlarni (`APP_KEY`, `OPENAI_API_KEY`, `TELEGRAM_BOT_TOKEN`) bu katalogga commit qilmang.
