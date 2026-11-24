# Check-In & Occupancy

The workspace has four main tabs: **Room Board**, **Guest Stay Account**, **Departure Incidents** and **Activity**. Guest details and their original read-only cleaning records select Guest Stay Account as their parent. Rooms-only staff retain their permitted Room Board and preparation views, without guest-account navigation.

| Work | Start here | Controls retained |
| --- | --- | --- |
| Arrival | Room Board → available room → Check In (standalone form) | Readiness, critical faults, occupancy, captured overnight/short-stay rate, guest fields and received payment |
| Guest charges and receipts | Guest Stay Account → Show Details | One stay identity, extras/stock links, incidental approval, authenticated cashier, exact amounts and source-bound receipts |
| Normal departure | Guest details → Check Out | Outstanding-balance guard, full-payment/departure confirmations, owner-only debt exception, actual departure snapshot and dirty-room transition |
| Unauthorized departure | Guest details → Report Unauthorised Departure, or Departure Incidents | Provisional truth, access security, owner review, actual-time confirmation, retained reports and collection follow-up |
| Money received after departure | Owner's permitted recovery action in guest details | Late receipt recovery remains distinct from a new outstanding collection; original receipts, checkout snapshot and activity dates stay linked |
| Room preparation | Room Board → Needs Cleaning / Cleaning In Progress / Awaiting Inspection | Assigned cleaner, captured checklist, independent inspection, owner self-inspection exceptions, critical-fault and stale-cycle guards |
| Review | Guest details → Bill / Payments / Statement Preview / Stay Activity | Statement is a separate preview; payment history and source-linked corrections remain visible; administrators can view activity for the selected stay |
| Operational history | Activity, or preparation's Cleaning History | Existing administrator versus own-activity permissions and pagination remain authoritative |

When an action form is open, its identical entry link for that same stay/room is hidden; the form's submit and cancel controls remain. Other records keep their own actions. Outstanding collections have one entry in guest details instead of a duplicate incident-panel button. Charge corrections/review refunds still open the existing source-linked financial workflow; a correction is not treated as money returned.

Use the primary Room Board tab to return to current preparation work. Cleaning History and owner-only Review use shared preparation navigation. The read-only original cleaning record has a booking-permission-gated **Back To Guest Stay Account** link to the actual source stay. It does not substitute a later guest or create a new room action.

Existing saved URLs, legacy billing rules, query filters, guest document access, receipts, statements, audit rows, confirmation tokens, CSRF, operation keys and all write services remain unchanged. No activation or database migration is needed for these presentation changes.
