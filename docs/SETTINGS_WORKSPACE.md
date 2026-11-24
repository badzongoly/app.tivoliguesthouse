# Settings workspace

Settings uses one shared tab row, in the same order everywhere:

| Section | Purpose |
| --- | --- |
| Payment Lists | Existing MoMo providers; legacy bank list remains when Finance is not installed. |
| Document Types | Classifications used by private staff documents. |
| Cleaning Checklist | Checks captured by future cleaning attempts. |
| Inspection Checklist | Checks captured by future inspections. |
| Audit Areas | Named areas available to weekly audits. |
| Extra Categories | Available category names/statuses for extras. |
| Notifications | Personal delivery preferences/history and existing owner-only reminder controls. |

Only sections compatible with the account's existing endpoint permissions and installed features are linked. The actual selected section is highlighted. Settings is selected in the sidebar for authorized settings users; ordinary staff opening their own notification preferences retain Reminders as their sidebar context. Existing saved URLs and deep links remain valid.

List Add/Edit controls are expandable. Open the relevant editor to focus its input. **Cancel** resets only that form's unsaved fields, closes it and focuses its summary. It makes no POST and never changes a stored setting. Without JavaScript the native editors and normal save forms remain usable; enhanced Cancel buttons stay hidden. Checklist Add controls remain per section because they target different captured sections; item removal remains an individual protected action.

HR lists require an actual administrator plus hr.settings to change. Existing non-administrator list readers retain their permitted read access with one visible read-only explanation per list. Operational checklist/area/category pages retain their original read boundary; editors additionally require all privileges checked by the existing StayService writer, including bookings.manage. Read access does not imply change authority.

Notifications keeps My Preferences and My Delivery Log separate from configured-owner-only Reminder Rules, Contact Verification and Delivery Services. Personal consent, exact-contact verification, source permissions, encrypted events, readiness checks and existing delivery history remain unchanged. **Check Now** is available on the operational Reminders page, reached with Back To Reminders; it is not repeated while editing Settings. No delivery service, credentials, scheduler or channel was enabled by this change.

Values, IDs, active/inactive entries and existing references are retained. Renaming/deactivating HR lists does not delete staff document/salary references. Cleaning and inspection edits affect future checks; started/completed attempts retain their captured checklists. Audits retain recorded area names; historical extras retain inactive category names. Existing CSRF, reasons, revision/version checks, operation keys, replay handling and atomic audit remain authoritative. No bulk reset, automatic grant or new writer was added.

Verified locally on the isolated synthetic services:44presentation,49focused Settings HTTP,13Reminder HTTP,47reported Reminder integration assertions,165browser assertions across33responsive views,271shared-modal and61asset checks. All124,645pre-existing protected synthetic access/settings/HR/guest/financial rows retained exactly. No ordinary settings/data, schema, permission grant, Git commit/push or hosted deployment changed. See [delivery evidence](delivery/changes/2026-11-23-settings-consolidation.md).
