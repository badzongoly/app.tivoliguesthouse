# Guest billing and checkout review · 13 November 2026

## Scope and status

The owner authorized an audit and prototype following weekly reconciliation. Status is proposal for approval. This task does not implement the proposed application behavior or modify application financial records. Sample guests, amounts and dates in the prototype are synthetic. Stay integration tests ran only on the verified dedicated synthetic instance.

## Observed current behavior

- Check-in stores a rate snapshot, guest registration, optional identity document and payment attestation. It does not create a payment or receipt. Evidence in src/Service/StayService.php, checkin branch, and templates/stays/index.php. The interface explicitly asks staff to record the amount separately on the account. This leaves a missing-receipt gap despite the SOP's paid-at-arrival rule.
- Ordinary overnight billing follows the next-day noon rule with a minimum of one night. At noon exactly the next night has not started. One second later another night is due. Short stay applies one fixed session rate and records a two-hour due time without an automatic overrun fee. Evidence in StayRules::nights and StayService::bill.
- Room, extras and approved incidentals contribute to the balance. Pending incidentals remain excluded. Pay-now extras record both the charge and receipt. Receipts and refunds retain links. Existing tariff changes preserve applied stay rates.
- Checkout checks the current expected balance, reviews, keys and authorization. A valid debt approval is capped at the authorized balance. Every administrator can currently grant this exception. The prototype proposes limiting authority to the designated finance owner in line with the SOP.
- Checkout retains departure charges, receipt balance, actor and timestamp. It marks the room dirty for cleaning. Frozen departures remain stable when later viewed. Ordinary payments after checkout are denied. The separate owner recovery workflow remains the appropriate route for qualifying late receipts.
- The improved guest statement uses the guest account bill. The improved booking CSV also calls that bill and discloses unknown historical rates. CSV output is capped at 100 rows. Finance and Owner Reports read completed room revenue from stay_departures, with their existing coverage filters and dates.
- Historical extras can have fractional values. The live bill rounds the sum in SQL while the itemized screen computes each line in minor units. These paths need fixed historical fixtures and explicit reconciliation before any shared calculation is changed.
- Physical departure without advance owner approval remains an operating gap. The SOP requires the actual event and residual debt to be retained. A later implementation needs a separate reported-departure incident/escalation path so staff can record factual departure without approving credit or fabricating payment.

## Proposed workflow shown

1. Register guest and show the applied rate and first covered accommodation period.
2. Receive Cash or MoMo and record an amount covering at least the initial room charge. Confirm check-in and receipt together in one operation. Keep documents optional. The intended implementation must atomically record occupancy, payment, receipt and audit with retry protection.
3. Keep guest details above the account. Show charges, receipts and balance separately, with each receipt's cashier and method. Show the next ordinary overnight charge boundary.
4. Review the account at departure. Require confirmation for full settlement and return to checkout. If debt remains, show a specific owner authorization with reason and capped amount. A larger balance invalidates its applicability.
5. Confirm actual departure and retain final charges, timestamp and debt or credit. The final statement lists the receipts already recorded. The room enters cleaning.
6. Preserve unknown historical evidence explicitly. Reconcile exports and reports using their actual definitions rather than treating receipts as completed-room revenue.

## Decisions requiring approval

- Add an amount and mandatory Cash/MoMo method to arrival. Enforce at least the first overnight charge or fixed short-stay charge before key issue. This extends the earlier five mandatory guest fields and makes the SOP payment rule enforceable.
- Limit unpaid-departure authorization to Eugene's designated account and require an authorization reason. Earlier UI allowed any administrator.
- Retain the existing noon boundary and fixed short-stay overrun behavior. No automatic late fee or conversion is proposed.
- Prototype nationality choices are abbreviated. The application should retain its existing full country list.

The factual unpaid-departure incident route and independent comparison of historical rounding remain separate design decisions before implementation. The prototype demonstrates approved unpaid checkout only.

## Executed evidence

C:/xampp/php/php.exe tests/Integration/stays.php passed all 137 assertions on the marked synthetic database. This confirmed current noon/short-stay calculations, snapshot stability, unchanged payment records, checkout guards, retry behavior, audit rollback and the existing admin-authorized unpaid departure path.

The proposed interface was exercised in isolated Chrome for underpaid arrival denial, combined sample check-in/receipt, extras and incidentals, canceled payment, full settlement returning to checkout, owner approval, invalidation after increased debt, retained unpaid balance, noon boundary, fixed short-stay rate and frozen final statement. Four prototype screens fit 1024, 736, 390 and 320 pixel widths. The desktop screenshot was visually inspected.

Prototype is tivoli-guest-billing-checkout.html in the conversation visualization directory. The prototype changes no application behavior. Approval should precede implementation, source-report reconciliation tests and independent review.
