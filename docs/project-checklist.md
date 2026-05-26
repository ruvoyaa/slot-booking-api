# Project Checklist

Этот документ фиксирует текущий статус проекта по этапам.

Правило ведения:

- после завершения каждого этапа статус и краткий результат должны обновляться;
- если этап разбивается на подэтапы, они добавляются ниже без переписывания уже закрытых решений;
- открытые вопросы не должны оставаться только в чате, их нужно отражать здесь или в `docs/spec.md`.

## Stage Checklist

- [x] Stage 1. Discovery and decisions freeze
  - Зафиксированы базовые решения по слоту, hold, quantity, JWT, Redis, counters-модели и expiry-поведению.
- [x] Stage 2. Project bootstrap
  - Создан Laravel bootstrap, поднят runtime-каркас, возвращена проектная документация, настроен Git и remote, первый коммит отправлен в `origin/main`.
- [x] Stage 3. Persistence
  - Добавлены миграции `slots` и `holds`, зафиксированы основные индексы и ограничения, схема проверена через `php artisan migrate:fresh`.
- [x] Stage 4. Auth skeleton
  - Добавлены custom JWT login/me flow, middleware, привязка текущего пользователя, локальная проверка пройдена на `127.0.0.1:8000`.
- [x] Stage 5. Service layer
  - Добавлены `SlotService`, доменные статусы, модели `Slot`/`Hold`, транзакции, idempotency, cache invalidation и lock-механика; сервисный smoke-test пройден, база возвращена в чистое seeded-состояние.
- [x] Stage 6. API layer
  - Добавлены `AvailabilityController` и `HoldController`, маршруты посажены на `SlotService`, HTTP smoke-проверка `availability -> hold -> confirm -> cancel` пройдена, база возвращена в seeded-состояние.
- [x] Stage 7. Tests
  - Добавлены 10 feature-тестов по availability, hold, confirm, cancel, oversell, auth и idempotency; suite проходит полностью (`10/10`), результаты зафиксированы в `docs/test-cases.md`.
- [x] Stage 8. README and curl scenarios
  - README приведён к runnable-формату: запуск, миграции, seeded user, auth flow, curl-сценарии и оговорки по локальному cache backend зафиксированы.
- [x] Stage 9. Local verification
  - Повторно подтверждены `migrate:fresh --seed`, `php artisan test`, seeded-состояние базы, внешний runtime на `0.0.0.0:8000`, внешний `GET /api/slots/availability` и внешний `POST /api/auth/login`.
- [x] Stage 10. Review and fixes
  - Закрыты review-риски по конкурентной идемпотентности `hold`, продуктовые допущения переведены в явные решения, test suite повторно подтверждён (`10/10`).

## Current Focus

Следующий этап:

- MVP stage set завершён, дальше только следующий функциональный цикл или подготовка к релизу.

Текущие открытые вопросы перед/внутри реализации:

- открытых продуктовых вопросов по текущему MVP больше не осталось; дальнейшие решения уже относятся к следующему расширению функциональности.
