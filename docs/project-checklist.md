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
  - Создан Laravel bootstrap, поднят runtime-каркас, возвращена проектная документация, настроен Git и remote.
- [ ] Stage 3. Persistence
  - Миграции `slots`, `holds`, уточнение ограничений и индексов.
- [ ] Stage 4. Auth skeleton
  - Минимальная custom JWT-авторизация, middleware, привязка текущего пользователя.
- [ ] Stage 5. Service layer
  - `SlotService`, транзакции, идемпотентность, cache invalidation, lock-механика.
- [ ] Stage 6. API layer
  - `GET /api/slots/availability`
  - `POST /api/slots/{id}/hold`
  - `POST /api/holds/{id}/confirm`
  - `DELETE /api/holds/{id}`
- [ ] Stage 7. Tests
  - Feature-тесты по availability, hold, confirm, cancel, oversell, auth, idempotency.
- [ ] Stage 8. README and curl scenarios
  - Инструкции запуска, миграции, auth, примеры curl и сценарии проверки.
- [ ] Stage 9. Local verification
  - Проверка `php artisan migrate`, локального запуска, Redis, curl-сценариев.
- [ ] Stage 10. Review and fixes
  - Финальная проверка рисков, регрессий и корректности счетчиков.

## Current Focus

Следующий этап:

- Stage 3. Persistence

Текущие открытые вопросы перед/внутри реализации:

- можно ли одному пользователю иметь несколько hold на один слот;
- нужно ли хранить полный прошлый response для идемпотентности.
