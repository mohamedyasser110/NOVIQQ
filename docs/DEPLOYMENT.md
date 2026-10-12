# DEPLOYMENT — Company OS (SQLite dev → Postgres prod)

## Local (Windows)
```
cd 'C:\Downloads\team mangment\apps\api'; npm install
$env:DATABASE_URL='file:./dev.db'; npx prisma generate --schema ../../prisma/schema.prisma
npx prisma migrate dev --schema ../../prisma/schema.prisma --name init
npm run seed; npm run start:dev   # :4100/api/health
cd ..\web; npm install; npm run dev  # :3100
```

## Production (Postgres)
1. Provision managed Postgres + `DATABASE_URL=postgresql://…` via secret manager (never commit).
2. `prisma migrate deploy --schema prisma/schema.prisma` (additive only; no reset).
3. Set `BOOTSTRAP_ENABLED=false`, rotate `JWT_*_SECRET`, remove demo users (`founder*@example.test`, `DEMO-001`).
4. HTTPS + `Secure` cookies + security headers (helmet on) + daily `pg_dump` encrypted + restore drill (see Phase 5).
5. `npm audit` clean, E2E A–G green.

## Switching SQLite → Postgres
Change `datasource db { provider = "postgresql" }` in `prisma/schema.prisma`, keep all `Int` money fields (minor units portable), re-run `prisma migrate dev`, run money-math suite (no float anywhere).
