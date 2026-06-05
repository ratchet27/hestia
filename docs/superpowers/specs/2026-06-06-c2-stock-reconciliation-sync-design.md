# C2 — Make min-stock → shopping-list reconciliation synchronous & self-correcting

**Date:** 2026-06-06
**Issue:** #54 (C2, Critical · bug)
**Review ref:** `docs/reviews/2026-06-05-backend-architecture/README.md` § C2
**Area:** backend / stock + shopping-list + messaging

## Problem

The "below-min → add auto item; restock → remove" rule (spec §12) is implemented as a
decoupled domain event — good — but the wiring has three coupled problems:

1. It is routed to the **async** RabbitMQ transport for a single one-query reaction. If the
   `messenger` worker (`compose.yaml:37`) is down, the shopping list **silently never
   updates** — and "what to buy" is a core feature on a self-hosted box.
2. The *same* reconciliation is also called **synchronously and directly** from the
   product-edit path (`ProductService.php:145-147`), a divergent second mechanism.
3. The handler trusts a **quantity snapshot** (`message.newQuantity`). Tests route the
   transport to `in-memory://` (≈sync) and assert only that a message was *dispatched*, so
   production's async path and a stale/reordered snapshot are never exercised.

## Goal

Keep the event decoupling (stock code must not import shopping-list code), **drop the broker
hop**, and make the reaction **self-correcting** by re-querying current stock. Converge the
product-edit path onto the same single mechanism.

## Decisions (confirmed)

- **Mechanism:** keep `StockChangedMessage` + `StockChangedHandler`; make the message
  *unrouted* so Messenger runs the handler **synchronously in-process**. Preserves the
  decoupling seam; removes only the RabbitMQ hop.
- **Message shape:** `StockChangedMessage` carries **only `productId`**. The handler
  re-queries, so quantity fields are not load-bearing and are removed (they invite the same
  bug back).
- **Error handling:** the handler wraps reconciliation in **try/catch + log**. It now runs in
  the user's request *after* the stock change has committed, so a reconciliation hiccup is
  logged (warning) and swallowed — the user's stock op always succeeds, and the next stock
  change self-corrects.

## Architecture & data flow

Unchanged seam:

```
StockEntryService / ProductService
        └─ dispatch(StockChangedMessage(productId))   // after flush() → stock committed
              └─ StockChangedHandler::__invoke         // now synchronous, in-process
                    └─ try { ShoppingListService::handleStockChange(productId) } catch → log
                          └─ deficit = max(0, minStock − countByProduct(productId))   // re-query
```

Dispatch already happens after `flush()` at every site, so the stock change is committed
before the handler runs. With the message unrouted, the handler executes inline at
`dispatch()` time and is idempotent because it derives the deficit from a fresh count.

## Component changes (production)

### `config/packages/messenger.yaml`
- Remove the `'App\Message\StockChangedMessage': async` routing line. Keep
  `'App\Message\SendDailyExpirySummary': async`. (The `when@test` override is unchanged; the
  message is now sync in both envs — that is the point.)

### `src/Message/StockChangedMessage.php`
- Reduce to a single property: `public Uuid $productId`. Drop `previousQuantity` /
  `newQuantity`. Update the class doc.

### `src/Service/StockEntryService.php` (dispatch sites :98, :143, :172, :224)
- Dispatch `new StockChangedMessage($productId)`.
- Drop the `$previousQty` / `$newQty` computations that existed **only** to feed the message.
- Keep any `countByProduct` / `countByProductAndLocation` calls still needed for response
  payloads (e.g. `ConsumeResultResponse.remaining_at_location`).

### `src/Service/ShoppingListService.php`
- Inject `StockEntryRepository`.
- Signature → `handleStockChange(Uuid $productId): void`.
- Compute `deficit = max(0, $product->getMinStock() - $this->stockEntryRepository->countByProduct($productId))`.
- Keep the active/`null` product guard and the upsert/remove auto-item logic.
- Remove the stale `_previousQty` "kept for the event contract" param + comment
  (`:32-33`). Re-verify and update the `minStock=0` comment (`:43`) and the
  `@infection-ignore-all: Equivalent mutant` note (`:44`) against the re-query.

### `src/MessageHandler/StockChangedHandler.php`
- Call `handleStockChange($message->productId)`.
- Inject `LoggerInterface`; wrap the call in try/catch, logging at warning level on failure
  (include `productId` context). The catch lives **here**, not in the service, so it covers
  the event-driven path only and catches before Messenger wraps it in
  `HandlerFailedException`.

### `src/Service/ProductService.php` (:145-147)
- When `minStock` changed, `dispatch(new StockChangedMessage($id))` instead of calling
  `ShoppingListService::handleStockChange` directly.
- Inject `MessageBusInterface`; remove the `ShoppingListService` dependency and the
  `StockEntryRepository` dependency **iff** nothing else in the class uses them.
- Keep the existing `minStock !== $oldMinStock` guard (dispatch only on change).

## Testing

`tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php` is the focus and the
bulk of the work.

- **Rewrite the ~20 direct-call tests** that currently pass a fake `newQty` (e.g.
  `handleStockChange($id, 3, 2)`) and create **no** `StockEntry` rows. After the re-query they
  would all see count=0. Seed real `StockEntry` rows via `StockEntryFactory` to establish the
  intended count, and call `handleStockChange($id)`.
- **Convert the 5 "dispatches message" tests** (`testConsumeStockDispatchesMessage`,
  `testAddStockDispatchesMessage`, `testConsumeStockDispatchesExactlyOneMessage`,
  `testAddStockDispatchesMessageWithCorrectQuantities`,
  `testDeleteEntryDispatchesMessageWithCorrectQuantities`). They pull from
  `messenger.transport.async`, which is now empty. Re-point them to assert **in-request
  reconciliation**: after `POST /stocks/add` | `/stocks/consume` | delete, the shopping list
  reflects the change within the same request (no worker).
- **Add an idempotency test:** dispatching twice for the same product produces exactly one
  auto item (no phantom duplicate).
- **Add a product min-stock edit test:** editing `minStock` reconciles via the same
  mechanism, in-request.
- Drop assertions that read `message.newQuantity` / `previousQuantity` (fields removed).

## Acceptance criteria

- `StockChangedMessage` is handled synchronously: consume/add/delete reflects on the shopping
  list within the same request, no worker required.
- `handleStockChange` recomputes from current stock (re-query) → idempotent and
  order-independent; the message quantity fields no longer exist.
- Product min-stock edits and stock changes go through one mechanism with identical behavior.
- A reconciliation failure is logged and never fails the user's committed stock operation.
- `SendDailyExpirySummary` remains async.
- `make lint` + `make test` green, with tests proving in-request auto-add/auto-remove and
  idempotency.

## Verification

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
grep -n "StockChangedMessage\|SendDailyExpirySummary" config/packages/messenger.yaml  # StockChanged no longer async
```

## Out of scope (do NOT)

- Remove the message/handler abstraction (keep the decoupling).
- Rewrite onto Symfony EventDispatcher.
- Add a `Stock` aggregate, pessimistic locking, or full-transaction consume.
- Change `SendDailyExpirySummary` (stays async).
- Touch the frontend.

## References

Review doc § C2 · spec §12 · book ACWA Ch.7 ("События")
