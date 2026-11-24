# Reminders & Escalations

Open **Overview → Reminders**, or the **Reminders** link in the workspace header. Each person sees reminders for their currently authorized work. **Show Details → Open Existing Workflow** takes you to the original task. Complete the action there. Reading a reminder never approves, pays, closes, releases a room or changes a deadline.

## Everyday use

- **To Act On** shows current tasks. **History** retains earlier reminders; original case histories remain on their workflow pages.
- **Mark As Read** records who read the reminder and when. Outstanding work can be reminded again on the next daily cycle.
- The configured owner can use **Check Now**, review **Owner Exceptions**, and see incomplete coverage or pending batches. A successful manual check is separate from a background heartbeat.
- Original responsibility and deadlines remain authoritative. Overdue, Critical or unassigned work goes to the owner. Different cash custodians receive their own actionable deposit reminders. Dispatched or disputed cash links to the existing handoff/discrepancy rather than falsely clearing its gross deposit duty.
- Staff completion remains pending where the original workflow requires owner verification. Missing sources remain unavailable; revoked permissions remove private details.

## Settings

Notification Settings is also available under **Settings → Notifications**, using the [shared Settings workspace](SETTINGS_WORKSPACE.md). Personal and configured-owner sub-settings remain distinct. **Check Now** stays on Reminders; use Back To Reminders from Settings.

**My Account → Notification Preferences** (or **Reminders → Notification Settings**) lets each user enable Email and WhatsApp for their own account. In-app reminders remain available. Optional channels start off. Email uses the account email address; WhatsApp needs an international number and explicit consent. Turning a channel off cancels unsent deliveries. Changing a contact invalidates its previous verification.

The configured owner alone manages **Reminder Rules**, **Contact Verification** and **Delivery Services**. Independently establish contact ownership and record that evidence before enabling delivery. Verification grants delivery only, never application privileges.

Daily reminders default to 09:00 UTC. Critical obligations appear at the next background check. One daily summary replaces repeated individual ordinary messages. Old unsent summaries are retained as cancelled instead of sending a backlog when a service returns. Select an explicitly authorized **Weekly Auditor**; grant the person's original **Audit Duty** first if necessary. No staff member is inferred from their name. Sunday reminders use original room/area registers. Late first-captured historical scope remains **Historical Scope Requires Review**; the app never invents past audit coverage or silently completes it.

## Delivery and background operation

**My Delivery Log** distinguishes setup pending, contact verification, queued, accepted, delivered, failed and unknown outcomes, with retained attempts and signed receipts. Provider acceptance is not proof of delivery. Definite failures retry at most three times, at least ten minutes apart. Unknown outcomes are held for review; never clear a claim or manually resend an uncertain message without independent provider evidence.

External messages are generic and link to secure sign-in; guest, salary and payment details remain in the app. Localhost and synthetic tests cannot send real messages. Real Email/WhatsApp require separately protected service credentials, a secure deployed address, verified contacts, WhatsApp consent and an approved operational template. See `resources/notification-services.example.php` for blank configuration names. Configure secrets outside version control. Provider connectivity, incoming opt-out handling and real delivery must be verified before external rollout; this local implementation does not activate a live provider.

**Local status:** the five-minute task is installed, and its first scheduled run completed successfully. No real Email/WhatsApp messages were sent.

The application worker is `scripts/reminders-worker.php`. It requires an explicit mode: `--test` is restricted to the isolated synthetic database; `--local` pins the confirmed local target; `--production` requires separate explicit configuration and approved target hash. Do not use a browser refresh as scheduling. The local Windows installer is `scripts/Register-TivoliReminderTask.ps1`: without `-Install` it only reports status; `-Install` registers the fixed local worker every five minutes for the signed-in Windows user. It refuses a different task with the same name. XAMPP must be running. Production scheduling is a separate deployment action.

Check **Last Background Check** and any incomplete-source warning. No heartbeat means automation has not run; a stopped computer or database prevents checks. Critical notifications supplement human safety procedures and cannot guarantee emergency delivery.

## Integrity and recovery

Notifications use encrypted, zero-value operational journal events, unique durable notice/delivery claims, version checks, persistent operation keys and required atomic audit. Concurrent workers cannot produce another claim for the same critical action. A genuinely new linked finding can create a fresh obligation; changing notes/deadlines cannot manufacture another one.

No migration, financial backfill or historical rewrite is required. Original receipts, charges, costs, refunds, stock, closed snapshots, source IDs and staff links remain unchanged. The reminder system never executes a source payment, approval, repair, deduction or write-off.

To disable automatic checks, disable only **Tivoli-Local-Reminders-5min** in Windows Task Scheduler. Keep all journal/claim/operation/audit and original source records. Continue using the existing workflows and owner register. Correct source adapters or delivery configuration forward; do not erase history, reopen financial periods or blindly resend unknown messages.
