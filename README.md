# DailyPro

**"Copio-pego un roadmap -> el sistema me crea calendario + tablero + recordatorios."**

DailyPro elimina la friccion entre "tengo un plan" y "lo estoy ejecutando". Recibe un plan en texto libre, lo normaliza con IA, lo calendariza segun tu disponibilidad, y lo publica en Trello + Google Calendar con recordatorios diarios.

## Stack

- **Framework:** Laravel 12 (PHP 8.2+)
- **DB:** MySQL (SQLite para tests)
- **IA:** OpenAI API (gpt-4o-mini por defecto)
- **Kanban:** Trello API
- **Calendar:** Google Calendar API (OAuth)
- **Queue:** Database driver (configurable)

---

## Arquitectura

```
Hexagonal / Ports & Adapters
============================

┌─────────────────────────────────────────────────────┐
│  HTTP Layer (Controllers / Requests / Resources)    │
│  routes/api.php                                     │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│  Application Layer                                  │
│  ┌─────────────┐ ┌──────────────┐ ┌──────────────┐  │
│  │ PlanService  │ │ Scheduler    │ │ PlanText     │  │
│  │ (use cases)  │ │ Service      │ │ Hasher       │  │
│  └──────┬───────┘ └──────────────┘ └──────────────┘  │
│         │                                            │
│  ┌──────▼───────────────────────────────────────┐    │
│  │ Contracts (Ports / Interfaces)               │    │
│  │  - AiNormalizerInterface                     │    │
│  │  - KanbanProviderInterface                   │    │
│  │  - CalendarProviderInterface                 │    │
│  └──────────────────────────────────────────────┘    │
└──────────────────────┬───────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│  Infrastructure (Adapters)                          │
│  ┌───────────────┐ ┌────────────┐ ┌──────────────┐  │
│  │ OpenAI        │ │ Trello     │ │ Google Cal   │  │
│  │ Normalizer    │ │ Provider   │ │ Provider     │  │
│  └───────────────┘ └────────────┘ └──────────────┘  │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  Domain (Models / Enums)                            │
│  Plan, PlanWeek, PlanTask                           │
│  PlanStatus, TaskStatus, ValidationStatus           │
└─────────────────────────────────────────────────────┘
```

### Patrones usados

| Patron | Donde |
|---|---|
| **Hexagonal (Ports & Adapters)** | Contracts (ports) vs Infrastructure (adapters) |
| **Strategy** | KanbanProviderFactory / CalendarProviderFactory |
| **Factory** | Provider factories para instanciar por nombre |
| **DTO** | CreatePlanDTO, RescheduleDTO, AvailabilitySlotDTO |
| **Service Layer** | PlanService orquesta todos los use cases |
| **Job/Queue** | PublishPlanJob, DailyRunJob |
| **Idempotency** | Hash SHA-256 en plan creation + publish guard |

---

## Estructura de directorios

```
app/
├── Application/
│   ├── Contracts/          # Ports (interfaces)
│   ├── DTOs/               # Data Transfer Objects
│   └── Services/           # Use cases y servicios puros
├── Domain/
│   └── Enums/              # PlanStatus, TaskStatus, ValidationStatus
├── Exceptions/             # Custom exceptions
├── Http/
│   ├── Controllers/        # API controllers
│   ├── Requests/           # Form request validation
│   └── Resources/          # API Resources (JSON transform)
├── Infrastructure/
│   ├── AI/                 # OpenAI adapter
│   ├── Calendar/           # Google Calendar adapter + factory
│   └── Kanban/             # Trello adapter + factory
├── Jobs/                   # PublishPlanJob, DailyRunJob
├── Mail/                   # DailyPlanMail
└── Models/                 # Eloquent models

tests/
├── Fakes/                  # Fake implementations for testing
├── Feature/                # Feature tests (endpoint tests)
└── Unit/                   # Unit tests (pure services)
```

---

## Esquema de Base de Datos

```
plans
├── id (PK)
├── hash (unique, SHA-256 para idempotencia)
├── plan_text (text)
├── settings (JSON)
├── normalized_json (JSON, nullable)
├── schedule (JSON, nullable)
├── validation_status (enum: pending|valid|invalid|needs_input)
├── publish_status (enum: draft|publishing|published|needs_update)
├── trello_board_id (nullable)
├── trello_board_url (nullable)
├── google_calendar_id (nullable)
└── timestamps

plan_weeks
├── id (PK)
├── plan_id (FK -> plans)
├── week_number (int)
├── goal (string)
└── timestamps

plan_tasks
├── id (PK)
├── plan_id (FK -> plans)
├── plan_week_id (FK -> plan_weeks)
├── title (string)
├── estimate_hours (decimal)
├── status (enum: pending|in_progress|done)
├── scheduled_date (date, nullable)
├── scheduled_start (time, nullable)
├── scheduled_end (time, nullable)
├── trello_card_id (nullable)
├── google_event_id (nullable)
└── timestamps
```

---

## Setup local

### 1. Clonar e instalar

```bash
git clone <repo-url> dailypro
cd dailypro
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configurar DB

Editar `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dailypro
DB_USERNAME=root
DB_PASSWORD=root
```

### 3. Correr migraciones

```bash
php artisan migrate
```

### 4. Configurar variables de integracion

```env
# OpenAI
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini

# Trello (https://trello.com/power-ups/admin)
TRELLO_KEY=your-trello-key
TRELLO_TOKEN=your-trello-token
TRELLO_WEBHOOK_SECRET=optional-secret

# Google Calendar
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
GOOGLE_ACCESS_TOKEN=your-oauth-token

# Email de recordatorio
DAILYPRO_REMINDER_EMAIL=you@example.com
```

### 5. Levantar servidor

```bash
php artisan serve
# O con queue worker:
php artisan serve & php artisan queue:work
```

---

## Variables .env

| Variable | Requerida | Descripcion |
|---|---|---|
| `OPENAI_API_KEY` | Si | API key de OpenAI |
| `OPENAI_MODEL` | No | Modelo a usar (default: gpt-4o-mini) |
| `TRELLO_KEY` | Si | Trello API key |
| `TRELLO_TOKEN` | Si | Trello API token |
| `TRELLO_WEBHOOK_SECRET` | No | Secret para validar webhooks |
| `GOOGLE_CLIENT_ID` | Si | Google OAuth client ID |
| `GOOGLE_CLIENT_SECRET` | Si | Google OAuth client secret |
| `GOOGLE_REDIRECT_URI` | Si | URI de callback OAuth |
| `GOOGLE_ACCESS_TOKEN` | Si | Access token obtenido via OAuth |
| `DAILYPRO_REMINDER_EMAIL` | No | Email para recordatorios diarios |

---

## Correr tests

```bash
# Todos los tests
php artisan test

# Solo unit tests
php artisan test --testsuite=Unit

# Solo feature tests
php artisan test --testsuite=Feature

# Un test especifico
php artisan test --filter=CreatePlanTest

# Con coverage (requiere Xdebug/PCOV)
php artisan test --coverage
```

Tests usan SQLite in-memory y fakes para todas las integraciones externas (OpenAI, Trello, Google Calendar).

---

## OAuth Google Calendar (pasos)

1. Ir a [Google Cloud Console](https://console.cloud.google.com/)
2. Crear proyecto o seleccionar existente
3. Habilitar **Google Calendar API**
4. Ir a **Credentials** > **Create Credentials** > **OAuth 2.0 Client ID**
5. Application type: **Web application**
6. Authorized redirect URIs: `http://localhost:8000/api/auth/google/callback`
7. Copiar `Client ID` y `Client Secret` al `.env`
8. Obtener access token via OAuth flow (puedes usar [OAuth Playground](https://developers.google.com/oauthplayground/)):
   - Scopes: `https://www.googleapis.com/auth/calendar`
   - Autorizar y copiar el access token al `.env` como `GOOGLE_ACCESS_TOKEN`

> En produccion usarias un refresh token flow. Para MVP, el access token manual es suficiente.

---

## Flujo de uso

```
1. IMPORT    →  POST /api/plans          (enviar texto del plan)
2. PREVIEW   →  Respuesta incluye normalized_json + schedule
3. PUBLISH   →  POST /api/plans/{id}/publish  (crea Trello board + Calendar events)
4. RESCHEDULE → POST /api/plans/{id}/reschedule  (cambia disponibilidad)
5. DAILY RUN →  POST /api/plans/{id}/daily-run   (o automatico via scheduler)
```

---

## API Endpoints

### 1. Crear plan (POST /api/plans)

Ingesta y normalizacion del plan. Idempotente por hash.

```bash
curl -X POST http://localhost:8000/api/plans \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "plan_text": "Semana 1: Aprender routing y controllers en Laravel. Hacer CRUD basico (3h). Leer docs oficiales (2h).\nSemana 2: Eloquent ORM y migraciones. Crear modelos y relaciones (4h). Seeders y factories (1.5h).\nSemana 3: Testing con PHPUnit. Unit tests (2h). Feature tests (2h). Mocking (1.5h).\nSemana 4: Deploy. Configurar servidor (2h). CI/CD con GitHub Actions (2h). Monitoreo (1h).",
    "settings": {
      "timezone": "America/Argentina/Buenos_Aires",
      "start_date": "2025-02-03",
      "availability": [
        {"day": "mon", "start": "20:00", "end": "21:30"},
        {"day": "tue", "start": "20:00", "end": "21:30"},
        {"day": "wed", "start": "20:00", "end": "21:30"},
        {"day": "thu", "start": "20:00", "end": "21:30"},
        {"day": "fri", "start": "20:00", "end": "21:30"}
      ],
      "hours_per_week": 7.5,
      "kanban_provider": "trello",
      "calendar_provider": "google",
      "reminders": {"email": true}
    }
  }'
```

**Respuesta exitosa (201 Created):**

```json
{
  "data": {
    "id": 1,
    "hash": "a1b2c3d4e5f6...",
    "plan_text": "Semana 1: Aprender routing...",
    "settings": {
      "timezone": "America/Argentina/Buenos_Aires",
      "start_date": "2025-02-03",
      "availability": [...],
      "hours_per_week": 7.5,
      "kanban_provider": "trello",
      "calendar_provider": "google",
      "reminders": {"email": true}
    },
    "normalized_json": {
      "title": "Plan de Estudio Laravel",
      "timezone": "America/Argentina/Buenos_Aires",
      "start_date": "2025-02-03",
      "weeks": [
        {
          "week": 1,
          "goal": "Aprender routing y controllers",
          "tasks": [
            {"title": "Hacer CRUD basico", "estimate_hours": 3},
            {"title": "Leer docs oficiales", "estimate_hours": 2}
          ]
        }
      ]
    },
    "schedule": {
      "slots": [
        {
          "week": 1,
          "task_title": "Hacer CRUD basico",
          "date": "2025-02-03",
          "start": "20:00",
          "end": "21:30",
          "minutes": 90
        }
      ],
      "warnings": []
    },
    "validation_status": "valid",
    "publish_status": "draft",
    "publication": {
      "trello": {"published": false, "board_id": null, "board_url": null},
      "google_calendar": {"published": false, "calendar_id": null}
    },
    "weeks": [...],
    "created_at": "2025-02-03T10:00:00+00:00",
    "updated_at": "2025-02-03T10:00:00+00:00"
  }
}
```

**Respuesta idempotente (200 OK):** Mismo body si el hash coincide.

**Error de validacion (422):**

```json
{
  "message": "The plan text field must be at least 10 characters.",
  "errors": {
    "plan_text": ["The plan text field must be at least 10 characters."]
  }
}
```

**Error de normalizacion IA (422):**

```json
{
  "type": "normalization_error",
  "title": "Plan Normalization Failed",
  "detail": "AI normalization failed after 3 attempts.",
  "errors": {"weeks.0.tasks": ["The weeks.0.tasks field is required."]}
}
```

---

### 2. Consultar plan (GET /api/plans/{id})

```bash
curl -X GET http://localhost:8000/api/plans/1 \
  -H "Accept: application/json"
```

**Respuesta (200 OK):** Mismo formato que POST /plans.

**Error (404):**

```json
{
  "message": "No query results for model [App\\Models\\Plan] 999"
}
```

---

### 3. Publicar plan (POST /api/plans/{id}/publish)

Crea tablero Trello + eventos Google Calendar. Idempotente.

```bash
curl -X POST http://localhost:8000/api/plans/1/publish \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Publicacion asincrona (via queue):**

```bash
curl -X POST http://localhost:8000/api/plans/1/publish \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"async": true}'
```

**Respuesta sincrona exitosa (200):**

```json
{
  "data": {
    "id": 1,
    "publish_status": "published",
    "publication": {
      "trello": {
        "published": true,
        "board_id": "60a1b2c3d4e5f6",
        "board_url": "https://trello.com/b/abc123/plan"
      },
      "google_calendar": {
        "published": true,
        "calendar_id": "primary"
      }
    }
  }
}
```

**Respuesta asincrona (202):**

```json
{
  "message": "Plan publish queued.",
  "plan_id": 1,
  "status": "publishing"
}
```

**Error de publicacion (502):**

```json
{
  "type": "publish_error",
  "title": "Publish Failed",
  "detail": "Trello API returned 401 Unauthorized"
}
```

---

### 4. Recalendarizar (POST /api/plans/{id}/reschedule)

```bash
curl -X POST http://localhost:8000/api/plans/1/reschedule \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "availability": [
      {"day": "mon", "start": "18:00", "end": "20:00"},
      {"day": "wed", "start": "18:00", "end": "20:00"},
      {"day": "fri", "start": "09:00", "end": "12:00"}
    ],
    "start_date": "2025-03-01",
    "hours_per_week": 10
  }'
```

**Respuesta (200 OK):** Plan completo con schedule actualizado. Si estaba publicado, `publish_status` cambia a `"needs_update"`.

---

### 5. Webhook Trello (POST /api/webhooks/trello)

Recibe callbacks de Trello cuando una card se mueve a "Hecho".

```bash
curl -X POST http://localhost:8000/api/webhooks/trello?token=your-secret \
  -H "Content-Type: application/json" \
  -d '{
    "action": {
      "type": "updateCard",
      "data": {
        "card": {"id": "card_abc123"},
        "listAfter": {"name": "Hecho"},
        "listBefore": {"name": "Hoy"}
      }
    }
  }'
```

**Respuesta procesada (200):**

```json
{"status": "processed", "task_updated": true}
```

**Respuesta ignorada (200):**

```json
{"status": "ignored", "reason": "irrelevant_action"}
```

**Auth invalida (401):**

```json
{"type": "authentication_error", "title": "Invalid webhook secret"}
```

---

### 6. Daily run (POST /api/plans/{id}/daily-run)

Ejecuta rutina diaria manualmente (mueve cards a "Hoy" + envia email).

```bash
curl -X POST http://localhost:8000/api/plans/1/daily-run \
  -H "Accept: application/json"
```

**Respuesta (200):**

```json
{
  "message": "Daily run completed.",
  "plan_id": 1,
  "date": "2025-02-03",
  "today_tasks": [
    {
      "id": 1,
      "title": "Hacer CRUD basico",
      "status": "pending",
      "scheduled_start": "20:00",
      "scheduled_end": "21:30"
    }
  ]
}
```

---

## Scheduler (logica de calendarizacion)

El scheduler distribuye tareas en los bloques de disponibilidad declarados:

- Itera semana por semana segun el plan normalizado
- Construye slots disponibles para la semana (lun-dom segun `availability`)
- Asigna tareas greedily: llena cada slot hasta completar `estimate_hours`
- Si una tarea no entra en un slot, la divide en multiples slots
- Si la semana no tiene suficiente disponibilidad, emite un `warning` de tipo `overflow`
- Si una tarea queda sin asignar, emite un `warning` de tipo `unscheduled`

---

## Idempotencia

- **Creacion:** Se calcula `SHA-256(plan_text_normalizado + settings_ordenados)`. Si el hash ya existe, devuelve el plan existente con status 200.
- **Publicacion:** Si el plan ya esta publicado, devuelve el estado actual sin recrear tablero/eventos.
- **Webhook:** Si la tarea ya esta en status `done`, el webhook no la modifica de nuevo.

---

## Jobs y Scheduler

- `PublishPlanJob`: Publica a Trello + Calendar en background (3 reintentos, 30s backoff)
- `DailyRunJob`: Mueve cards a "Hoy" + envia email
- Cron schedule: `DailyRunJob` corre automaticamente a las 07:00 para todos los planes publicados

```bash
# Para activar el cron:
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Manejo de errores

Errores siguen estilo "Problem Details":

| Codigo | Tipo | Cuando |
|---|---|---|
| 200 | OK | Operacion exitosa / idempotente |
| 201 | Created | Plan creado por primera vez |
| 202 | Accepted | Publish encolado (async) |
| 401 | Auth error | Webhook secret invalido |
| 404 | Not found | Plan no existe |
| 422 | Validation | Input invalido o normalizacion IA fallida |
| 502 | Bad gateway | Fallo en servicio externo (Trello/Google) |

---

## Tests

```
83 tests, 189 assertions

Unit (41 tests):
- PlanTextHasherTest (9): hash consistency, normalization, SHA-256 format
- NormalizedPlanValidatorTest (11): valid/invalid plans, all field validations
- SchedulerServiceTest (10): scheduling, splitting, overflow, warnings
- AvailabilitySlotDTOTest (4): from/to array, duration
- KanbanProviderFactoryTest (4): supports, make, unknown
- CalendarProviderFactoryTest (4): supports, make, unknown

Feature (42 tests):
- CreatePlanTest (13): success, idempotent, validation errors, AI failure
- ShowPlanTest (3): full structure, publication status, 404
- PublishPlanTest (6): success, idempotent, 404, 502, external IDs
- ReschedulePlanTest (5): new availability, needs_update, validation, 404
- TrelloWebhookTest (9): HEAD, mark done, idempotent, ignore, auth, empty
- DailyRunTest (2): today tasks, 404
```

