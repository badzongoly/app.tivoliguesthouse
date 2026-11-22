# Reminders & Escalations — proposed approval prototype

22 November 2026. Owner requested an interactive approval prototype, simple existing-workflow integration, in-app delivery and optional Email/WhatsApp choices in Settings. No application implementation, real delivery setup, scheduler, data change, privilege grant, Git mutation or production deployment is authorized by prototype review controls. Explicit chat approval governs later implementation.

## Recommended approach

Use one shared source-linked reminder inbox plus the existing Owner Exception Register. A reminder opens the original task. Reading, external delivery, follow-up notes and source completion remain distinct. Do not create another task/case/financial ledger or parallel approve/pay/close form. Alternative separate tickets would duplicate case identities; implementing every business action inside reminders would duplicate safety/financial guards. Personal channel preferences are optional delivery choices, not authority over sources.

## Normal path and proposed defaults

Header reminder entry → To Act On → Show Details → Open Existing Workflow. History retains reminders, reads, source-related notification changes and delivery outcomes. Reading does not remove an unresolved responsibility. One current source card, not one card per send attempt.

Proposed for owner review: daily reminders/ordinary external summary at 09:00 UTC, first overdue check escalates to Eugene, Critical source conditions notify Eugene immediately. Ordinary pending approvals notify the authorized action owner when their existing source needs action. Repeat outstanding reminders at most once per day. Already-with-owner work does not create another self-escalation. These timings are proposals, not existing approved operating policy. Original due dates remain on source pages, including the unchanged gross next-day cash deposit obligation. Do not invent a due date for an undated payroll/recovery source.

Weekly routine reminders use the Sunday audit requirement and explicitly delegated audit duty. They link existing room/area audit registers and check actual required audit coverage. Missing responsibility goes to Eugene; no account is guessed or granted duty. Routine keys identify schedule/week, not fabricated audit findings. Actual generation must define due coverage against active configured rooms/areas at the intended cutoff.

Sources illustrated: Cash & MoMo discrepancy, weekly room/audit repair/completion/verification, purchase approval/delivery/cost/payout verification, private payroll recovery, stock disposition, departed-stay recovery and weekly audit registers. Other existing source modules can use the same adapter contract without re-entry. Unsupported legacy coverage from Owner Exceptions remains explicit; the reminder feature must not pretend all historical settlements are consolidated.

## Settings and external delivery

Every individual account can open My Preferences, regardless of permission to administer shared settings. In-app remains enabled. Email and WhatsApp start off. Users opt in for themselves using verified notification contacts, with explicit WhatsApp consent and opt-out. A changed contact invalidates its verification before future delivery. No arbitrary third-party recipient or credentials field in this prototype. Global service setup/rules belong to authorized administration; My Preferences does not confer that authority.

An enabled preference is Waiting For Setup or Waiting For Contact Verification until prerequisites pass. Readiness is distinct from opt-in. Global service setup requires a checked sender/mail service or connected WhatsApp business service with permitted message format. Secrets belong in protected server configuration, not public forms, host widget state or audit payloads. Opting out cancels unsent deliveries and preserves prior send history. All reminders remain accessible in-app.

Default external messages are generic: operational work requires review, sign in securely. Do not expose guest names/ID documents, balances, payroll details, bank/MoMo destinations or private reasons. External links require the secure deployed app URL; localhost will not work from staff phones. Link access must recheck the current account and source permissions and must never carry bearer access to a case. No direct approve/pay action from email or WhatsApp.

Ordinary work is grouped into one daily summary per recipient/channel/slot; Critical alerts have their own original source/condition identity. Do not queue/send multiple copies when an administrator rechecks the page or a worker retries. Enabling a channel catches up only currently actionable work, not every missed historic day.

Provider accepted, sent, delivered, read and source completed are separate states. Email acceptance does not prove inbox delivery. WhatsApp delivered requires its provider receipt; duplicate receipts must not create duplicate history. Definite failures use bounded retries/backoff; the prototype previews at most three attempts. An ambiguous timeout/unknown outcome is held for provider reconciliation, never blindly resent. External exactly-once delivery cannot be promised solely by a local idempotency key. In-app remains the fallback and delivery health needs owner visibility.

Official policy checked: [WhatsApp Business Messaging Policy](https://business.whatsapp.com/policy) (current page updated 23 September 2026), recipient permission/opt-out and business-initiated permitted template requirements. [Meta status messages reference](https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/reference/messages/status/) distinguishes sent/delivered/read/failed and provider callbacks. Recheck the selected provider's current capabilities at implementation; the prototype does not select or activate a provider.

## Source identity, authority and retry controls for later implementation

Authoritative key = source module + original root ID + real obligation/episode + recipient + channel + intended slot. A changed comment cannot mint a new episode. A genuine supported reopened Cash & MoMo case uses the same original case ID plus its explicit reopen event; prior verified amounts remain retained. Repeated supported reopenings need distinct monotonic episodes. Closed weekly audits do not gain a new reopen command.

Use durable notification/outbox uniqueness and operation keys for preference/rule changes, reminder generation, escalation and delivery-status ingestion. Repeated scheduling/read/replay creates no duplicate critical record. Critical source writes continue using their original durable idempotency keys, CSRF, locks, fresh source versions and mandatory atomic audit. The scheduler never posts receipts/refunds/costs, writes off debt, deducts salary or releases rooms.

Before dispatch, recheck current source state, responsibility, account activity, permissions, opt-in, verified destination and service readiness. Cancel or exclude resolved/reassigned/revoked members, without erasing prior history. Never downgrade a missing/stale source to resolved. Source failures produce explicit incomplete coverage and an operational owner alert. Staff completion transitions to the existing owner-verification responsibility; it does not itself resolve the case. Repair, audit closure and room readiness retain separate source controls.

A protected background worker/scheduler with monitoring, concurrency locks and bounded catch-up is needed for delivery while no browser is open. This design does not create a Codex reminder or rely on an open browser tab. Reconcile uncertain sends with provider IDs and preserve append-only attempts/audit separately from financial records. Unique row/attempt identity protects local retries without claiming impossible provider guarantees. Normal data, production setup and external test sends require their own authorized target and reviewed deployment.

## Prototype scope and evidence

Fictional cases and money, local interaction only. Role previews illustrate proposed visibility, not backend permission verification. Prototype progress/connection/contact/delivery controls are visibly outside the proposed product and send nothing. Host state records only recent sample interaction context, not a durable business audit or implementation approval. The normal product path has no simulated pay/approve/close controls. All application, database, settings and Git state remain untouched.

Offline verification exercises source identity/retries, read versus task completion, external preferences/consent/setup, send versus delivery, unknown-outcome hold, bounded failure retry, partial balances, repeated source reopenings retaining verification, source unavailability, private payroll limitations, role boundaries, staff completion versus owner verification, purchase cost versus actual payout verification, opt-out, host-state restoration and responsive layouts. Final executed counts are recorded in reminders-verification.json beside the prototype.
