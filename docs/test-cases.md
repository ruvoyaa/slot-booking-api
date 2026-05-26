# Test Cases

Этот документ фиксирует тест-кейсы проекта и их фактический статус после прогона.

Правила ведения:

- каждый кейс имеет стабильный номер;
- после прогона обновляются разделы `Что сделано`, `Что получили`, `Статус`;
- если поведение изменится, кейс обновляется, а не дублируется без причины.

## Тест 1. Логин и выдача JWT

- Цель:
  Проверить, что `POST /api/auth/login` принимает валидные учетные данные и возвращает bearer token.
- Что хотим получить:
  `200 OK`, `access_token`, `token_type=Bearer`, данные пользователя.
- Что сделано:
  Выполнен feature-тест `test_login_returns_jwt_token`.
- Что получили:
  `200 OK`, в ответе есть `token_type=Bearer`, `access_token`, `expires_in`, `user.id`, `user.email`.
- Статус:
  `passed`

## Тест 2. Доступность слотов

- Цель:
  Проверить, что `GET /api/slots/availability` возвращает seeded slots в ожидаемом формате.
- Что хотим получить:
  `200 OK`, массив слотов с `slot_id`, `capacity`, `remaining`.
- Что сделано:
  Выполнен feature-тест `test_availability_returns_slots`.
- Что получили:
  `200 OK`, API вернул 2 слота с полями `slot_id`, `capacity`, `remaining`.
- Статус:
  `passed`

## Тест 3. Успешное создание hold

- Цель:
  Проверить, что защищённый `POST /api/slots/{id}/hold` создаёт hold и уменьшает доступность.
- Что хотим получить:
  `201 Created`, статус `held`, корректный `quantity`, уменьшенный `remaining`.
- Что сделано:
  Выполнен feature-тест `test_hold_creation_succeeds_and_reduces_remaining`.
- Что получили:
  `201 Created`, hold создан со статусом `held`, `quantity=2`, `remaining` уменьшился с `5` до `3`.
- Статус:
  `passed`

## Тест 4. Идемпотентность hold

- Цель:
  Проверить, что повторный запрос с тем же `Idempotency-Key` не создаёт второй hold и не уменьшает доступность повторно.
- Что хотим получить:
  тот же hold, без двойного списания `held_count`.
- Что сделано:
  Выполнен feature-тест `test_hold_is_idempotent_for_same_user_and_key`.
- Что получили:
  Повтор с тем же `Idempotency-Key` вернул тот же hold, в БД осталась 1 запись, `held_count` не списался повторно.
- Статус:
  `passed`

## Тест 5. Конфликт по оверселу

- Цель:
  Проверить, что при нехватке вместимости API возвращает конфликт.
- Что хотим получить:
  `409 Conflict`, hold не создаётся, counters не ломаются.
- Что сделано:
  Выполнен feature-тест `test_hold_returns_conflict_when_capacity_is_exhausted`.
- Что получили:
  `409 Conflict`, hold не создан, counters слота не изменились.
- Статус:
  `passed`

## Тест 6. Успешный confirm

- Цель:
  Проверить, что `POST /api/holds/{id}/confirm` переводит hold в `confirmed` и корректно переносит количество из `held_count` в `confirmed_count`.
- Что хотим получить:
  `200 OK`, статус `confirmed`, availability остаётся консистентной.
- Что сделано:
  Выполнен feature-тест `test_confirm_transfers_held_count_to_confirmed_count`.
- Что получили:
  `200 OK`, hold переведён в `confirmed`, `held_count` уменьшен до `0`, `confirmed_count` увеличен до `2`, availability осталась консистентной.
- Статус:
  `passed`

## Тест 7. Expired hold на confirm

- Цель:
  Проверить, что просроченный hold на confirm переводится в `expired`, освобождает `held_count` и возвращает `409 Conflict`.
- Что хотим получить:
  `409 Conflict`, статус `expired`, освобождённая доступность.
- Что сделано:
  Выполнен feature-тест `test_confirm_expired_hold_returns_conflict_and_releases_capacity`.
- Что получили:
  `409 Conflict`, hold переведён в `expired`, `held_count` освобождён, `remaining` восстановлен до полной вместимости.
- Статус:
  `passed`

## Тест 8. Успешная отмена hold

- Цель:
  Проверить, что `DELETE /api/holds/{id}` для hold в статусе `held` переводит его в `cancelled` и возвращает доступность.
- Что хотим получить:
  `200 OK`, статус `cancelled`, увеличенный `remaining`.
- Что сделано:
  Выполнен feature-тест `test_cancel_held_hold_returns_capacity`.
- Что получили:
  `200 OK`, hold переведён в `cancelled`, `remaining` увеличился обратно до исходного значения.
- Статус:
  `passed`

## Тест 9. Защита маршрутов

- Цель:
  Проверить, что защищённые hold endpoints без bearer token не доступны.
- Что хотим получить:
  `401 Unauthorized`.
- Что сделано:
  Выполнен feature-тест `test_protected_hold_routes_require_bearer_token`.
- Что получили:
  `401 Unauthorized`, защищённый hold endpoint без bearer token недоступен.
- Статус:
  `passed`

## Тест 10. Несколько hold одного пользователя на один слот

- Цель:
  Проверить, что одному пользователю разрешено создать несколько hold на один и тот же слот при разных `Idempotency-Key`, пока хватает доступности.
- Что хотим получить:
  2 разных hold, корректное накопление `held_count`, без конфликта по самой модели.
- Что сделано:
  Выполнен feature-тест `test_same_user_can_create_multiple_holds_for_same_slot_with_different_keys`.
- Что получили:
  Созданы 2 разные записи hold, `held_count=2`, `remaining` уменьшился с `5` до `3`.
- Статус:
  `passed`

## Второй прогон на требуемом стеке

Этот прогон выполнен после перевода проекта на `Laravel 12`.

Фактическая среда второго прогона:

- `Laravel 12.60.2`
- `PHP 8.4`
- `mysql` driver через `PDO`
- `MariaDB 10.11` на `127.0.0.1:3306`
- `Redis` extension отсутствует, поэтому cache/lock path работает через безопасный fallback, а не через полноценный Redis runtime

Важно:

- это прогон на требуемом приложенческом стеке `Laravel 12 + SQL runtime`;
- база проверена на `MariaDB 10.11`; для текущего проекта и dev-сервера этого достаточно, отдельная замена на `MySQL 8` не требуется.

### Тест 1R. Логин и выдача JWT на требуемом стеке

- Цель:
  Повторно проверить `POST /api/auth/login` уже на runtime с `Laravel 12 + mysql driver`.
- Что хотим получить:
  `200 OK`, `access_token`, `token_type=Bearer`, seeded user.
- Что сделано:
  Выполнен HTTP smoke-run against `http://127.0.0.1:8011/api/auth/login`.
- Что получили:
  `200 OK`, возвращены `token_type=Bearer`, `access_token`, `expires_in`, `user.id=1`, `user.email=demo@example.com`.
- Статус:
  `passed`

### Тест 2R. Доступность слотов на требуемом стеке

- Цель:
  Повторно проверить `GET /api/slots/availability` на SQL runtime.
- Что хотим получить:
  `200 OK`, 2 seeded slot, `remaining` равен `5` и `10`.
- Что сделано:
  Выполнен HTTP запрос до `hold`.
- Что получили:
  `200 OK`, ответ: slot `1` с `remaining=5`, slot `2` с `remaining=10`.
- Статус:
  `passed`

### Тест 3R. Успешное создание hold на требуемом стеке

- Цель:
  Повторно проверить создание hold и уменьшение availability на SQL runtime.
- Что хотим получить:
  `201 Created`, `status=held`, `quantity=2`, `remaining` уменьшается с `5` до `3`.
- Что сделано:
  Выполнен `POST /api/slots/1/hold` с `Idempotency-Key: mysql-required-hold-1`.
- Что получили:
  Hold создан, `status=held`, `quantity=2`, availability после операции стал `remaining=3`.
- Статус:
  `passed`

### Тест 4R. Идемпотентность hold на требуемом стеке

- Цель:
  Проверить, что повтор с тем же `Idempotency-Key` на SQL runtime возвращает тот же hold.
- Что хотим получить:
  тот же `id`, без повторного списания доступности.
- Что сделано:
  Выполнен повторный `POST /api/slots/1/hold` с тем же `Idempotency-Key: mysql-required-hold-1`.
- Что получили:
  Возвращён тот же hold `id=1`, `remaining` повторно не уменьшался.
- Статус:
  `passed`

### Тест 5R. Конфликт по оверселу на требуемом стеке

- Цель:
  Повторно проверить `409 Conflict` при явном oversell уже на SQL runtime.
- Что хотим получить:
  `409 Conflict`, сообщение `Slot capacity is exhausted.`.
- Что сделано:
  Выполнен `POST /api/slots/1/hold` с `quantity=999`.
- Что получили:
  HTTP статус `409`, тело ответа: `{"message":"Slot capacity is exhausted."}`.
- Статус:
  `passed`

### Тест 6R. Несколько hold одного пользователя на один слот на требуемом стеке

- Цель:
  Проверить multi-hold сценарий на SQL runtime.
- Что хотим получить:
  второй hold создаётся при другом `Idempotency-Key`.
- Что сделано:
  Выполнен `POST /api/slots/1/hold` с `Idempotency-Key: mysql-required-hold-2` и `quantity=1`.
- Что получили:
  Создан второй hold `id=2`, `status=held`, конфликт по самой модели не возник.
- Статус:
  `passed`

### Тест 7R. Успешный confirm на требуемом стеке

- Цель:
  Повторно проверить confirm на SQL runtime.
- Что хотим получить:
  `200 OK`, `status=confirmed`.
- Что сделано:
  Выполнен `POST /api/holds/1/confirm`.
- Что получили:
  Hold `id=1` переведён в `confirmed`, `quantity=2`.
- Статус:
  `passed`

### Тест 8R. Успешная отмена hold на требуемом стеке

- Цель:
  Повторно проверить cancel на SQL runtime.
- Что хотим получить:
  `200 OK`, `status=cancelled`.
- Что сделано:
  Выполнен `DELETE /api/holds/2`.
- Что получили:
  Hold `id=2` переведён в `cancelled`.
- Статус:
  `passed`

### Тест 9R. Защита маршрутов на требуемом стеке

- Цель:
  Повторно проверить `401 Unauthorized` без bearer token.
- Что хотим получить:
  `401 Unauthorized`.
- Что сделано:
  Выполнен `POST /api/slots/1/hold` без токена.
- Что получили:
  HTTP статус `401`, тело ответа: `{"message":"Missing bearer token."}`.
- Статус:
  `passed`

### Тест 10R. Финальная доступность и консистентность после required-stack прогона

- Цель:
  Проверить, что после `hold -> confirm -> second hold -> cancel` итоговая доступность согласована.
- Что хотим получить:
  для slot `1` итоговый `remaining=3`, потому что `confirmed_count=2`, активных hold больше нет.
- Что сделано:
  Выполнен финальный `GET /api/slots/availability` после confirm/cancel сценариев.
- Что получили:
  Финальный ответ вернул `slot_id=1, capacity=5, remaining=3`, что соответствует ожидаемой counters-модели.
- Статус:
  `passed`
