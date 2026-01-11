## Notion Mini Frontend (Next.js)

Bu katalog frontend (Next.js) uchun. Loyiha bo‘yicha to‘liq setup va foydalanish:
- Root `README.md`

### Muhim: `/api` reverse-proxy
Frontend barcha backend chaqiruvlarini **`/api/*`** orqali proxy qiladi, shuning uchun:
- Desktop: `http://localhost:3000`
- Telefon: `http://<laptop-ip>:3000`

Docker’da `BACKEND_BASE_URL=http://backend:8000` env ishlatiladi.
