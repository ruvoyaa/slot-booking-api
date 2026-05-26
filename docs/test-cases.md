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
