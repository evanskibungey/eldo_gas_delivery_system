sta# EldoGas — Order Flow Fix & Improvement Plan

**Reviewed:** 2026-06-08  
**Scope:** Laravel 12 backend · Customer Flutter app · Rider Flutter app  
**Total tasks:** 17 across 6 phases  
**Execution order:** Phase 1 → 6. Do not skip phases — each phase unblocks the next.

---

## Status Legend

| Symbol | Meaning |
|--------|---------|
| ⬜ | Not started |
| 🔄 | In progress |
| ✅ | Done |

---

## PHASE 1 — Critical Bugs
> These are correctness and data-integrity failures. Nothing else should be built until these are fixed.

---

### ✅ Task 1 — Fix `correction_in_progress` in Rider App

**Problem:** When an admin sets an order to `correction_in_progress` (wrong cylinder, damaged cylinder), the rider app's `nextStatus` getter returns `null`, the action button disappears, and the status stepper shows nothing. The rider has no idea what is happening.

**Finding on inspection:** The `status_stepper.dart` and amber banner in `order_detail_screen.dart` were already implemented correctly. The only real gap was the missing `isBlocked` computed property on the model, causing the screen to use a magic string literal.

**Changes made:**

| File | Change |
|------|--------|
| `eldogas_riderapp/lib/features/orders/models/order_model.dart` | Added `bool get isBlocked => status == 'correction_in_progress'` computed property. Updated `nextStatus` doc comment to mention blocked state. |
| `eldogas_riderapp/lib/features/orders/screens/order_detail_screen.dart` | Replaced `order.status == 'correction_in_progress'` with `order.isBlocked`. |

**Acceptance test:** Rider opens an order in `correction_in_progress` → sees amber stepper + informational banner, no broken/missing button.

---

### ✅ Task 2 — Fix M-Pesa Payment Settlement

**Problem:** `payment_method = 'mpesa'` is stored throughout the system but no actual settlement happens. Every M-Pesa order stays `payment_status = 'pending'` permanently. `OrderService::collectPayment()` only handles cash.

**Immediate fix (Phase 1 — manual):**

| File | Change |
|------|--------|
| `app/Http/Controllers/Admin/OrderController.php` | Extend `collectPayment()` to accept M-Pesa orders. Remove the implicit cash-only guard. |
| `resources/js/Pages/Admin/Orders/Show.tsx` | Show "Mark M-Pesa Paid" button for delivered M-Pesa orders where `payment_status != 'collected'`. |
| `app/Models/Order.php` | Add helper method `needsPaymentConfirmation()`: returns true when `payment_method = mpesa` and `payment_status = pending` and `status = delivered`. |

**Foundation for full integration (Phase 1 — backend only, no UI):**

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/Webhooks/MpesaCallbackController.php` | Create. Receives Daraja STK Push callback. Verifies `CheckoutRequestID` against a stored reference. On success, finds order by reference and calls `OrderService::collectPayment()`. |
| `routes/api.php` | Register `POST /api/webhooks/mpesa/callback` → `MpesaCallbackController` (no auth middleware — Safaricom calls this). |
| `database/migrations/` | Add `mpesa_checkout_request_id` (nullable string) and `mpesa_merchant_request_id` (nullable string) columns to `orders` table. |
| `app/Models/Order.php` | Add the two new fields to `$fillable`. |

**Acceptance test:** Admin can manually mark an M-Pesa order as paid. The callback endpoint returns 200 and can receive a test payload without 500 errors.

---

### ✅ Task 3 — Admin Alert When Auto-Assignment Fails

**Problem:** If `AutoAssignRiderToOrder` finds no eligible rider, it only logs and does nothing. The order stays `pending` indefinitely with no admin notification.

**Files to change:**

| File | Change |
|------|--------|
| `app/Listeners/AutoAssignRiderToOrder.php` | After the "no eligible rider" log, call `SmsTemplateService::adminNewOrder()` with message: _"Order [order_number] could not be auto-assigned. Please assign a rider manually."_ Also broadcast `OrderPlacedEvent` again with a `requiresManualAssignment: true` flag so the admin dashboard can highlight it. |
| `app/Listeners/AutoAssignRiderToOrder.php` | Add a `failed(OrderPlacedEvent $event, \Throwable $e)` method that fires the same admin alert if the job exhausts all retries. |
| `resources/js/Pages/Admin/Orders/Index.tsx` | Highlight rows where `status = pending` and `created_at` is older than 5 minutes in amber. Add a count chip: _"X orders need manual assignment."_ |

**Acceptance test:** Place an order when no riders are available → admin receives SMS + dashboard shows the order highlighted in amber.

---

### ✅ Task 4 — Fix Stock Deduction Timing

**Problem:** `PlaceOrderAction` uses a pessimistic lock to *check* that stock > 0, but the actual deduction happens only when the rider marks `picked_up`. Between placement and pickup, the same stock unit can be claimed by multiple concurrent orders, causing overselling.

**Files to change:**

| File | Change |
|------|--------|
| `app/Actions/PlaceOrderAction.php` | Inside the existing pessimistic-lock transaction, after creating the Order, call `StockService::deductForOrder($order)` immediately. Remove the deduction call from the rider status update path. |
| `app/Services/Admin/StockService.php` | Rename `autoDeductForOrder()` to `deductForOrder()`. Remove the call from `OrderService::advanceStatus()` on `picked_up`. |
| `app/Actions/CancelOrderAction.php` | The stock restore already calls `StockService::autoRestoreForOrder()` — rename to `restoreForOrder()` for consistency. Verify it runs for all cancellation paths including `out_of_stock` action. |
| `app/Http/Controllers/Api/V1/Rider/OrderController.php` | Remove stock deduction from the `picked_up` status transition block. |

**Acceptance test:** Place two simultaneous orders for the last cylinder in stock — only one succeeds with a 200, the second gets a 422 `OUT_OF_STOCK` response.

---

## PHASE 2 — Repeat Customer Home Page Flow
> The single highest-impact UX change. Returning customers should be able to reorder in 2 taps from the home screen.

---

### ✅ Task 5 — "Refill Now" Quick-Order Button on Home Page

**Problem:** The home page last-order card has a "Reorder" button that seeds the draft and then navigates into the full 4-step funnel (type → size → brand → addons). A customer who wants the exact same gas again must tap through everything.

**Goal:** Tap "Refill Now" → land directly on ReviewOrderPage with all fields pre-filled → tap "Place Order".

**Files to change:**

| File | Change |
|------|--------|
| `user_curstomer_eldogas_app/lib/features/order/presentation/controllers/reorder_controller.dart` | Add `quickReorder(int orderId)` method. Logic: runs same validation as `reorder()`. On `ReorderReady`, sets a `goDirectlyToReview: true` flag in the result instead of navigating to the funnel entry. |
| `user_curstomer_eldogas_app/lib/features/home/presentation/pages/home_page.dart` | On the last-order card, change button label from "Reorder" → **"Refill Now"**. On tap, call `reorderController.quickReorder(lastOrder.id)`. On `ReorderReady` → `context.push(Routes.orderReview)`. On `ReorderUnavailable` → show snackbar with the reason and fall back to regular `context.push(Routes.orderInitiate)`. |
| `user_curstomer_eldogas_app/lib/features/home/presentation/pages/home_page.dart` | Show a subtitle on the last-order card: _"[Brand] [Size] — [Address label]"_ so the customer knows exactly what will be ordered before tapping. |
| `user_curstomer_eldogas_app/lib/features/order/presentation/pages/review_order_page.dart` | Ensure ReviewOrderPage works correctly when navigated to directly (all draft fields already populated). No changes needed if draft is pre-seeded — just verify. |

**Acceptance test:** Customer with a past delivered order opens the app → sees "Refill Now — Shell 13kg (Home)" card → taps → lands on ReviewOrderPage with size, brand, address, and payment method pre-filled → taps Place Order → success.

---

### ✅ Task 6 — Show GasPoints Balance and Savings on Home Page

**Problem:** GasPoints balance is only visible in the GasPoints tab and on ReviewOrderPage at the very end of checkout. Customers don't know they have points to use until the last step, so redemption rates are low.

**Files to change:**

| File | Change |
|------|--------|
| `user_curstomer_eldogas_app/lib/features/home/presentation/pages/home_page.dart` | Below or inside the last-order card, add a chip: _"💰 1,500 pts — save KES 150 on this refill"_. Only show when `gasPointsBalance >= 500` (minimum redemption tier). Pull balance from `homeControllerProvider` (already returned by `GET /home`). |
| `user_curstomer_eldogas_app/lib/features/home/data/home_api.dart` | Verify `HomeSummary` includes `gasPointsBalance`. If not, add it to the response DTO and backend `GET /api/home` response. |
| `app/Http/Controllers/Api/V1/Customer/HomeController.php` _(backend)_ | Ensure `gaspoints_balance` is included in the home response JSON. |

**Acceptance test:** Customer with ≥ 500 GasPoints sees a savings hint on the home page last-order card.

---

### ✅ Task 7 — Stale Draft Cleanup on Session Start

**Problem:** If a customer abandoned an order draft 3 days ago, reopening the app resumes mid-funnel with an outdated draft — potentially for a brand that is now out of stock.

**Files to change:**

| File | Change |
|------|--------|
| `user_curstomer_eldogas_app/lib/features/auth/presentation/controllers/session_controller.dart` | In `build()`, after restoring the session token, check the `order_draft` Hive box. If the draft's `updatedAt` timestamp is older than 24 hours, call `orderDraftController.reset()`. |
| `user_curstomer_eldogas_app/lib/features/order/domain/order_draft.dart` | Add `updatedAt: DateTime?` field to the `OrderDraft` Freezed model. |
| `user_curstomer_eldogas_app/lib/features/order/presentation/controllers/order_draft_controller.dart` | Set `updatedAt = DateTime.now()` on every mutation method (`setOrderType`, `setSize`, `setBrand`, etc.). |

**Acceptance test:** Leave an incomplete draft, change device clock forward 25 hours, reopen app → draft is cleared, home page shows normally.

---

## PHASE 3 — Order Completion Improvements

---

### ✅ Task 8 — Automatic Post-Delivery Rating Prompt

**Problem:** The rating button exists only in the History page. There is no automatic prompt when an order is delivered. Most customers will never find it voluntarily, leading to very low rating completion rates.

**Files to change:**

| File | Change |
|------|--------|
| `user_curstomer_eldogas_app/lib/features/order/presentation/controllers/order_tracking_controller.dart` | When a Pusher status event or polling result returns `status == 'delivered'`, set a `shouldPromptRating: true` flag on `OrderTrackingState`. |
| `user_curstomer_eldogas_app/lib/features/order/domain/order_tracking_state.dart` | Add `bool shouldPromptRating` field to the state model. |
| `user_curstomer_eldogas_app/lib/features/order/presentation/pages/tracking_page.dart` | Add a `ref.listen` on `orderTrackingControllerProvider`. When `shouldPromptRating` transitions to `true`, show a modal bottom sheet: _"Your gas arrived! Rate your experience."_ — same `RatePage` content embedded in the sheet. On dismiss/submit, navigate to home. |

**Acceptance test:** Order reaches `delivered` while tracking page is open → rating sheet automatically appears without any navigation required.

---

### ✅ Task 9 — Show Delivery Photo to Customer

**Problem:** The rider uploads a proof-of-delivery photo, stored in `orders.delivery_photo_path`, but no customer-facing UI or API response exposes it. Customers cannot confirm delivery and disputes are harder to resolve.

**Files to change:**

| File | Change |
|------|--------|
| `app/Http/Resources/Api/V1/Customer/OrderDetailResource.php` _(backend)_ | Add `'delivery_photo_url' => $this->delivery_photo_path ? Storage::url($this->delivery_photo_path) : null` to the resource array. |
| `user_curstomer_eldogas_app/lib/features/order/domain/order_detail.dart` | Add `String? deliveryPhotoUrl` field to the `OrderDetail` Freezed model. Add to `fromJson`. |
| `user_curstomer_eldogas_app/lib/features/order/presentation/pages/history_page.dart` (order detail view) | When `deliveryPhotoUrl != null` and `status == delivered`, show a "Proof of Delivery" section with a tappable thumbnail that opens a full-screen image viewer. |

**Acceptance test:** Rider uploads a delivery photo → customer opens order history → sees "Proof of Delivery" image on the completed order.

---

### ✅ Task 10 — Add ETA to Tracking Page

**Problem:** The tracking page shows the rider's live location on a map but displays no estimated arrival time. Customers are left anxious with no idea when to expect their gas.

**Files to change:**

| File | Change |
|------|--------|
| `app/Events/RiderAssignedEvent.php` _(backend)_ | Compute `estimated_minutes` using Haversine distance between rider's last known location and order `delivery_lat/lng`, divided by a configurable average speed (default: 30 km/h urban). Include in broadcast payload. |
| `app/Http/Resources/Api/V1/Customer/OrderDetailResource.php` _(backend)_ | Include `estimated_minutes` (recalculated on every detail fetch from current rider location). |
| `user_curstomer_eldogas_app/lib/features/order/domain/order_detail.dart` | Add `int? estimatedMinutes` field. |
| `user_curstomer_eldogas_app/lib/features/order/presentation/controllers/order_tracking_controller.dart` | Update `estimatedMinutes` from both WebSocket events and polling responses. |
| `user_curstomer_eldogas_app/lib/features/order/presentation/pages/tracking_page.dart` | Display _"~12 min away"_ prominently below the order status bar. Hide once `status == delivered`. |
| `config/app.php` or `SystemSetting` _(backend)_ | Add `delivery_avg_speed_kmh` setting (default: 30). Allows admin to tune the estimate per city. |

**Acceptance test:** Order is assigned to a rider 3 km away → tracking page shows "~6 min away". Estimate updates as rider moves.

---

### ✅ Task 11 — Address Confirmation Highlight After Reorder

**Problem:** When a reorder seeds the draft with a past order's address, the customer may have moved or want to deliver to a different address. The ReviewOrderPage silently pre-fills the old address without drawing attention to it.

**Files to change:**

| File | Change |
|------|--------|
| `user_curstomer_eldogas_app/lib/features/order/domain/order_draft.dart` | Add `bool seededFromReorder` flag to `OrderDraft`. |
| `user_curstomer_eldogas_app/lib/features/order/presentation/controllers/order_draft_controller.dart` | Set `seededFromReorder = true` in the `reorder()` seed flow. Clear it to `false` once the user manually confirms or changes the address. |
| `user_curstomer_eldogas_app/lib/features/order/presentation/pages/review_order_page.dart` | When `seededFromReorder == true`, highlight the address selector with an amber border and show a tooltip: _"Delivering to [Home Address] — tap to change."_ Remove highlight once customer acknowledges. |

**Acceptance test:** Customer taps "Refill Now" → ReviewOrderPage shows address row highlighted in amber with hint text. After tapping to confirm address, highlight disappears.

---

## PHASE 4 — Rider & Assignment Improvements

---

### ⬜ Task 12 — Rider Accept / Decline Mechanism

**Problem:** Riders are assigned silently with no ability to accept or decline. If a rider is unavailable (at lunch, outside service area, etc.) despite their toggle being "online", the order stalls.

**Backend changes:**

| File | Change |
|------|--------|
| `app/Models/Order.php` | Add `rider_acceptance_deadline` (nullable timestamp) and `rider_accepted_at` (nullable timestamp) columns via migration. |
| `app/Http/Controllers/Api/V1/Rider/OrderController.php` | Add `POST /api/rider/orders/{order}/accept` and `POST /api/rider/orders/{order}/decline` endpoints. On decline: call `OrderService::riderDeclined()` which unassigns rider and re-runs `AutoAssignRiderToOrder` with the declining rider excluded. On accept: set `rider_accepted_at = now()`. |
| `app/Services/Admin/OrderService.php` | Add `riderDeclined(Order $order, Rider $rider)` method. Resets to `pending`, fires `OrderPlacedEvent` to retrigger auto-assignment. If no other eligible rider, alerts admin. |
| `app/Console/Commands/ExpireRiderAcceptance.php` | Create scheduled command (every 1 minute): finds `rider_assigned` orders where `rider_acceptance_deadline < now()` and `rider_accepted_at = null`. Calls `riderDeclined()` to reassign. |

**Rider app changes:**

| File | Change |
|------|--------|
| `eldogas_riderapp/lib/features/dashboard/screens/dashboard_screen.dart` | The new order modal sheet currently has only "Go to Orders" button. Replace with **"Accept"** and **"Decline"** buttons. Show a countdown timer (e.g., 60 seconds). |
| `eldogas_riderapp/lib/features/orders/services/orders_service.dart` | Add `acceptOrder(int id)` and `declineOrder(int id)` methods. |

**Acceptance test:** Rider receives assignment → sees 60-second countdown on modal → taps Decline → order is reassigned to next available rider. Timer expires → same auto-reassign happens.

---

### ⬜ Task 13 — Proximity Radius Filter in Auto-Assignment

**Problem:** `AutoAssignRiderToOrder` sorts riders by load and experience but has no location filter. A rider 25 km away could be auto-assigned ahead of one 500 m away.

**Files to change:**

| File | Change |
|------|--------|
| `app/Listeners/AutoAssignRiderToOrder.php` | After filtering for `is_active && is_available && no active orders && recent location`, add a Haversine distance filter: only include riders whose last known location is within `X km` of the shop location. Default X = 15 km. |
| `app/Models/SystemSetting.php` or `config/app.php` | Add `auto_assign_radius_km` setting (default: 15). |
| `database/seeders/SystemSettingsSeeder.php` | Add the setting to the seeder so it exists in all environments. |

**Acceptance test:** A rider 20 km away and a rider 2 km away are both online. Auto-assignment selects the closer rider.

---

## PHASE 5 — Admin & Backend Improvements

---

### ⬜ Task 14 — Admin Orders Index Real-Time Polling Fallback

**Problem:** The admin orders page receives new orders via the `admin.orders` WebSocket channel. If the WebSocket connection drops, new orders stop appearing until the page is manually refreshed.

**Files to change:**

| File | Change |
|------|--------|
| `resources/js/Pages/Admin/Orders/Index.tsx` | Add a `useEffect` that sets up a 30-second interval calling `Inertia.reload({ only: ['orders', 'statusCounts'] })` when the Pusher connection is disconnected. Listen to the `pusherConnectedProvider` (or equivalent Pusher JS `connection.bind('disconnected')`) to activate/deactivate the polling. When WebSocket is live, cancel the interval. |

**Acceptance test:** Disconnect the browser's WebSocket (DevTools → Network → block WS). Place a new order. Admin dashboard picks it up within 30 seconds via polling.

---

### ⬜ Task 15 — Pending Order Timeout Alert Command

**Problem:** If auto-assignment fails and the admin misses the SMS, an order can sit `pending` for hours with no escalation.

**Files to change:**

| File | Change |
|------|--------|
| `app/Console/Commands/AlertStalePendingOrders.php` | Create. Query orders where `status = 'pending'` and `created_at < now() - N minutes` (configurable, default 10 min). For each, call `SmsTemplateService::adminNewOrder()` with message: _"ALERT: Order [order_number] has been pending for [X] minutes. Please assign a rider."_ Prevent duplicate alerts with a `last_stale_alert_at` column or cache key per order. |
| `app/Console/Kernel.php` | Schedule `AlertStalePendingOrders` every 5 minutes. |
| `database/migrations/` | Add `last_stale_alert_at` (nullable timestamp) to `orders` table to prevent spam. |

**Acceptance test:** Place an order with no riders available. Wait 10 minutes (or reduce threshold in test). Admin receives SMS alert.

---

## PHASE 6 — Polish & Parity Audit

---

### ⬜ Task 16 — Website vs Mobile App Feature Parity Audit

**Problem:** The mobile app has significantly more features than the website. It is unknown whether the website's `OrderBuilder.tsx` supports GasPoints redemption, the website's `Tracking.tsx` has live rider location, and whether address management on the web matches the mobile flow.

**Audit checklist:**

| Feature | Mobile App | Website — Verify |
|---------|-----------|-----------------|
| GasPoints redemption slider | ✅ ReviewOrderPage | `OrderBuilder.tsx` — does it have the slider? |
| Address CRUD (create, set default) | ✅ | Web address management pages |
| Live rider location on tracking map | ✅ Pusher + polling | `Tracking.tsx` — does it use Pusher JS? |
| Order cancellation UI | ✅ | `Show.tsx` — is cancel button present? |
| Reorder from history | ✅ | `History.tsx` — is reorder available? |
| Post-delivery rating prompt | Will be added (Task 8) | `Rate.tsx` — is auto-prompt implemented? |
| Delivery photo proof | Will be added (Task 9) | `Show.tsx` — will need same addition |
| ETA on tracking | Will be added (Task 10) | `Tracking.tsx` — will need same addition |

**For each gap found:** implement the missing feature in the corresponding web component, following the same backend API additions made in earlier tasks. The backend changes in Tasks 8–10 serve both mobile and web.

**Acceptance test:** A customer on the website can complete the entire order flow — including GasPoints redemption, live tracking with ETA, and post-delivery rating — with the same experience as the mobile app.

---

## Dependency Map

```
Task 4 (stock timing)
  └── must complete before any load testing

Task 5 (Refill Now button)
  └── depends on Task 6 (GasPoints on home) — implement together
  └── depends on Task 7 (stale draft) — implement together

Task 8 (auto rating prompt)
  └── independent — can be done any time after Phase 1

Task 9 (delivery photo)
  └── backend change is independent
  └── mobile UI is independent

Task 10 (ETA)
  └── backend change is independent
  └── mobile UI is independent

Task 11 (address highlight)
  └── depends on Task 5 (Refill Now seeding flow)

Task 12 (rider accept/decline)
  └── depends on Task 3 (admin alert infrastructure already in place)

Task 13 (proximity filter)
  └── independent — can be done any time after Phase 1

Task 15 (stale order alert)
  └── depends on Task 3 (same SMS infrastructure)

Task 16 (website parity)
  └── depends on Tasks 9, 10 (backend additions must exist first)
```

---

## Quick Reference — All Files Changed

### Backend (`EldoGasDeliverySystem/`)

| File | Tasks |
|------|-------|
| `app/Actions/PlaceOrderAction.php` | 4 |
| `app/Actions/CancelOrderAction.php` | 4 |
| `app/Console/Commands/AlertStalePendingOrders.php` | 15 (new) |
| `app/Console/Commands/ExpireRiderAcceptance.php` | 12 (new) |
| `app/Console/Kernel.php` | 12, 15 |
| `app/Events/RiderAssignedEvent.php` | 10 |
| `app/Http/Controllers/Admin/OrderController.php` | 2 |
| `app/Http/Controllers/Api/V1/Customer/HomeController.php` | 6 |
| `app/Http/Controllers/Api/V1/Rider/OrderController.php` | 4, 12 |
| `app/Http/Controllers/Api/Webhooks/MpesaCallbackController.php` | 2 (new) |
| `app/Http/Resources/Api/V1/Customer/OrderDetailResource.php` | 9, 10 |
| `app/Listeners/AutoAssignRiderToOrder.php` | 3, 13 |
| `app/Models/Order.php` | 2, 12 |
| `app/Services/Admin/OrderService.php` | 2, 12 |
| `app/Services/Admin/StockService.php` | 4 |
| `config/app.php` or `SystemSetting` | 10, 13 |
| `database/migrations/` (multiple) | 2, 12, 15 |
| `routes/api.php` | 2, 12 |
| `resources/js/Pages/Admin/Orders/Index.tsx` | 3, 14 |
| `resources/js/Pages/Admin/Orders/Show.tsx` | 2 |

### Customer Flutter App (`user_curstomer_eldogas_app/`)

| File | Tasks |
|------|-------|
| `lib/features/auth/presentation/controllers/session_controller.dart` | 7 |
| `lib/features/home/data/home_api.dart` | 6 |
| `lib/features/home/presentation/pages/home_page.dart` | 5, 6 |
| `lib/features/order/domain/order_detail.dart` | 9, 10 |
| `lib/features/order/domain/order_draft.dart` | 7, 11 |
| `lib/features/order/domain/order_tracking_state.dart` | 8 |
| `lib/features/order/presentation/controllers/order_draft_controller.dart` | 7, 11 |
| `lib/features/order/presentation/controllers/order_tracking_controller.dart` | 8, 10 |
| `lib/features/order/presentation/controllers/reorder_controller.dart` | 5 |
| `lib/features/order/presentation/pages/history_page.dart` | 9 |
| `lib/features/order/presentation/pages/review_order_page.dart` | 11 |
| `lib/features/order/presentation/pages/tracking_page.dart` | 8, 10 |

### Rider Flutter App (`eldogas_riderapp/`)

| File | Tasks |
|------|-------|
| `lib/features/dashboard/screens/dashboard_screen.dart` | 12 |
| `lib/features/orders/models/order_model.dart` | 1 |
| `lib/features/orders/screens/order_detail_screen.dart` | 1 |
| `lib/features/orders/services/orders_service.dart` | 12 |
| `lib/features/orders/widgets/status_stepper.dart` | 1 |

---

## Progress Tracker

| # | Task | Phase | Severity | Status |
|---|------|-------|----------|--------|
| 1 | Fix `correction_in_progress` in rider app | 1 | Critical Bug | ⬜ |
| 2 | Fix M-Pesa payment settlement | 1 | Critical Bug | ⬜ |
| 3 | Admin alert on auto-assignment failure | 1 | Critical Bug | ⬜ |
| 4 | Fix stock deduction timing | 1 | Critical Bug | ⬜ |
| 5 | "Refill Now" quick-order button on home page | 2 | High UX | ⬜ |
| 6 | GasPoints balance on home page | 2 | High UX | ⬜ |
| 7 | Stale draft cleanup on session start | 2 | High UX | ⬜ |
| 8 | Auto rating prompt on delivery | 3 | High Feature | ⬜ |
| 9 | Delivery photo visible to customer | 3 | High Feature | ⬜ |
| 10 | ETA on tracking page | 3 | High Feature | ⬜ |
| 11 | Address confirmation highlight on reorder | 3 | Medium UX | ⬜ |
| 12 | Rider accept / decline mechanism | 4 | High Operational | ⬜ |
| 13 | Proximity radius filter in auto-assignment | 4 | Medium Operational | ⬜ |
| 14 | Admin orders index polling fallback | 5 | Medium Reliability | ⬜ |
| 15 | Pending order timeout alert command | 5 | Medium Reliability | ⬜ |
| 16 | Website vs mobile app parity audit | 6 | Medium Polish | ⬜ |

---

*Update the status column (⬜ → 🔄 → ✅) as each task is started and completed.*
