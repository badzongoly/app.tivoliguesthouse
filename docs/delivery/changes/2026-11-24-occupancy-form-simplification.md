# Occupancy form simplification

## Scope and safeguards

The Room Board uses a status list alongside room and guest search. A selected room's check-in opens in its own workspace view. Posting an extra returns focus to Stay Bill and gives brief feedback. Incidental review accepts an optional comment. Stay Activity is admin-only at the URL and in the stay data.

Check-in and checkout reminders are confirmed by their named submit buttons and the existing confirmation flow. The occupancy controller translates those submissions to the existing service attestation fields, which older callers can still submit directly. Checkout continues to require settlement or an owner-authorized exception. An unverified departure leaves the room blocked until a separate security verification. A charge correction request confirms its reminder on submit and still requires a supported correction, review and approval.

The Room Board starts a cleaning attempt through the existing housekeeping writer. The board obtains the selected room's current token before confirmation and the writer checks it again before starting. The final cleaning action still requires every captured checklist item and neither completion nor a request for exception releases a room. Housekeeping details show Activity & Evidence to admins. Earlier records and attachments remain stored.

## Executed verification

138 occupancy integration assertions and 136 HTTP assertions across occupancy navigation, housekeeping, paid arrival and checkout, departure incidents, guest charge corrections, and activity permissions. The final browser rerun passed 49 occupancy assertions across eight 320px views, including direct cleaning and Post Extra feedback. The earlier layout checks also rendered at 390px and 1440px. Shared Cancel and confirmation navigation passed 49 DOM checks after fixing form fields that shadowed the browser's form properties.

Tests used the marked synthetic database and web service only. PHP and JavaScript syntax checks passed. No schema change, ordinary database write, or hosted deployment was part of this change.

## Recovery

Revert the source slice if presentation must be restored. Keep the admin Stay Activity restriction if rolling back presentation independently. All recorded check-ins, receipts, departures, cleaning attempts and correction requests continue to use existing records and history.
