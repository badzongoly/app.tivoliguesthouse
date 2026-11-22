# Stock Costing & Profit Accuracy — 21 November 2026

## Authorization and scope

**Confirmed:** owner approved the interactive prototype and actual implementation, with simple existing workflows. **Implemented locally:** exact moving weighted average, owner opening/evidence review inside Finance Profit Review, links from Extras & Stock, automatic source capture from accepted purchasing, guest-extra stock movements, approved stock-count differences and non-sale issue decisions/disposition. Original summary contracts remain intact; the separate bridge adds eligible purchase expenses back once and recognizes stock consumed. Unknown costs and partial cutover are disclosed. Existing weekly/monthly snapshots can retain the disclosed cost basis.

Application/Git root: `C:/xampp/htdocs/app.tivoliguesthouse`, existing main checkout. The declared older Tivoli cwd does not exist. User's preexisting uncommitted refactoring was preserved; no staging, commit, branch changes or worktree creation/deletion, push or deployment. New code was staged externally and copied only through an explicit guarded path list.

## Financial and security consequences

Only the designated active finance owner with report authority can inspect or change supported costs/evidence. New data is excluded from other administrators' Finance Activity. CSRF, fresh source versions, persistent operation fingerprints and unique opening/source claims protect each critical command. Parent source writes and cost/audit journals share one transaction and lock. No stock/expense/payment is created by opening evidence; no second expense at disposal or supplier payment. Required audit failure rolls back the source decision and costing together.

The cutover day/ranges remain partial. Missing opening costs, unsupported movements and surplus valuation stay incomplete. Cost snapshots are recording-time cumulative states; eligible acquisition adjustments retain the source posting date. New valuation/classification deltas use an open period. Prior immutable opening/receipt/payment/expense/movement rows and closed snapshots remain retained. This is an additional management estimate, not a statutory accounting certification or historical cost fabrication.

## Independent review

**Executed:** fresh-context, read-only financial/security review (`/root/stock_costing_review`) of all new costing logic, source hooks, permissions/UI and saved-report changes. Four important findings reproduced failing before fixes: unrelated pre-cutover correction eligibility, backdated cumulative snapshots containing later sales, unsupported movements in historical coverage, and effective linked-opening quantity correction. All four now have regression tests. Additional tests cover late opening-source classification once, and excluding unsupported inventory from supported carrying value. No minor findings were reported. See the local implementation ledger for individual RED→GREEN evidence.

## Verification

Executed commands/results and final responsive verification are recorded in the final verification update below. Every dynamic check is restricted to the guarded synthetic database `127.0.0.1:3307/tivoli_auth_test`, marker `synthetic-auth-only-v1`, and the marked synthetic HTTP server at `127.0.0.1:8099`. No ordinary database records/configuration, SQL import or production system was used.

## Delivery / recovery

**Implemented**, not deployed to production. No migration/backfill or role setup. Owner-reviewed item openings are still required in the ordinary database before its automatic cost capture starts. Existing finance installation remains a prerequisite. Before openings, recover by restoring a coherent reviewed pre-feature code snapshot. After openings, retain all cost/source/operation/closed-report history, stop affected posting and use a source-linked fix forward; do not delete journals, change stock to force totals, or remove only some writer hooks. See [operator guide](../../STOCK_COSTING.md).


## Final executed verification

- **51 distinct automated suites / 1,483 assertions passed.** `C:/Python313/python.exe .../stock-costing/verify.py` ran the affected unit, integration/concurrency and HTTP/access/route checks sequentially. `verify-final.py` refreshed the costing and Finance HTTP results after source-boundary/owner-form changes. `C:/xampp/php/php.exe tests/Integration/stock_costing_fault.php` and `stock_costing_complete.php` each passed40 assertions afterward, including all shared source/purchase cases.
- Coverage includes exact weighted average and large integer rounding; supported/unknown/late opening costs; original-row equality; loss at approval, zero second disposal cost, complimentary supply; count/source/return/quantity corrections; closed snapshots, historical unsupported movements and late-date states; permissions/CSRF/malformed input; durable replay/concurrency; mandatory audit and source-query fault rollback/recovery; Pay Now receipt/sale/cost replay; and a fully covered profit bridge. Temporary test-cohort stock-setting masks were rolled back and exact restoration asserted; no financial originals were deleted or rewritten.
- **27 PHP files passed syntax checks** using `C:/xampp/php/php.exe -n -l`; new source SHA-256 hashes are retained with the local lint evidence. UTF-8/replacement-character checks and scoped source-hook/whitespace checks passed. The shared Finance CSS has one scoped rule to wrap long item headings on narrow phones.
- A real isolated PDO query fault was reproduced RED: the generic runtime-error handler incorrectly converted a database failure to unknown cost and allowed the original sale. Explicit PDO exception propagation now rolls the whole source transaction back; same-key recovery creates one original sale/cost.

Responsive verification also passed: twelve owner views at1440,390 and320 pixels, opening focus/visible action, cancelled confirmation, unknown-cost opening, supported evidence/value, and the profit bridge. No browser runtime errors or page overflow. Long stock names reproduced a narrow-phone heading overflow and now wrap with one scoped CSS rule. Screenshots were captured directly on each panel after resizing; selected desktop/mobile views were visually inspected. The browser check waits for the existing resize transition and accounts for CSS uppercase table labels. Local evidence files: the external `stock-costing/verification.json`, `lint-verification.json`, source-hook diff, individual command logs and browser evidence; these contain only synthetic fixtures/source metadata and are not a production release record.


Final status: implemented and tested locally; no deferred important review findings. Ordinary-database owner opening review remains a business setup task, not an automated backfill. Next roadmap workflow: Weekly Audit Follow-Up. The ordinary database, configuration, credentials, financial originals, Git state and production system were not modified by testing.
