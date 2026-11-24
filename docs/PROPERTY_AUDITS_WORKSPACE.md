# Property & Audits

One shared tab row is used by room administration, the out-of-order log and weekly audits:

- **Room Master:** room details and standing overnight/short-stay rates. Existing stays retain captured rates.
- **Out-Of-Order Log:** fault reporting, repair and retained supporting evidence. Critical priority retains its existing blocking rules.
- **Room Audits / Other Area Audits:** current-week registers, All Weeks, original findings and the existing Record Audit form.
- **Follow-Ups:** open and closed actions, original audit links, named assignment, completion, verification/rework and retained history.
- **Audit Duty:** configured owner only; authorize an eligible account to record audits. No financial or room-administration privileges are granted by audit duty.

Tabs reflect the existing route permissions. The Room Master and fault route require an administrator with rooms.manage and bookings.manage. Authorized auditors get the audit registers; other operational staff retain their permitted follow-ups. The owner alone assigns duty, responsible staff/deadlines, returns work and verifies closure. Administrative names never grant authority.

New visible room/area audit entry links use the canonical weekly-audits route. Existing saved legacy URLs, filters and original history remain supported. The original source page does not link to itself; older area-history pages retain Latest Original History plus their earlier-history cursor. Linked findings still open their original source. Passing audits do not create work; Needs Attention/Unsafe creates or explicitly links a follow-up. No association is guessed.

The Room Master no longer repeats cleaning/inspection actions. Use the already consolidated Check-In & Occupancy preparation workflow. Open room/fault/repair forms do not repeat their own entry for the same record; submit/cancel remain. Cancel returns to the page that opened the form, retaining its filters and selected tab; directly opened fault/repair forms retain the Out-Of-Order Log fallback. Room/area audit forms likewise return to Follow-Ups or their audit register according to entry. See [form cancellation](FORM_NAVIGATION.md). Status/readiness and original fault/repair evidence remain visible in Property & Audits.

Staff Mark Done still means Awaiting Verification. A later Pass cannot close outstanding work. Critical repairs, historical fault links and unsafe-area attestations are checked by the original services. Closing a follow-up does not release a room. Captured sources, actors, deadlines, comments, evidence, operation keys and all audit events remain unchanged.
