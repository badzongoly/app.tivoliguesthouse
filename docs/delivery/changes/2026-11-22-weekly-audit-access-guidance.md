# Weekly audit access and discoverability — 22 November 2026

Implemented and tested locally. Screenshot showed a clerk with only Follow-Ups and an empty list. Root cause: the configured owner had not granted that account Audit Duty, so the template deliberately omitted entry/register tabs, but explained neither the setup nor the later owner-only decisions. The previous workflow handoff did not make this prerequisite clear enough.

Changed only the presentation: templates/weekly-audits/index.php now explains missing Audit Duty on the follow-up landing page, provides direct Record Room Audit / Record Area Audit links to authorized auditors, and explains owner assignment/verification versus staff completion. No permission change, automatic duty grant, ordinary data write, migration, money-policy change, external delivery or hosted deployment. Existing writers, CSRF, persistent operation keys, source/history links and modal controls remain intact. All earlier uncommitted work is preserved. Low-risk UI slice; no independent security review claimed.

Executed evidence:

- C:/Python313/python.exe -u tests/Contract/weekly_audits_http.py: added six role/access discovery assertions, observed expected failure before fix, then all 28 passed. Includes actual duty denial, owner-only delegation/verification, direct forms, CSRF, completion retry, evidence access and historical pagination.
- C:/Program Files/nodejs/node.exe tests/Contract/weekly_audit_access_browser.cjs: 24 assertions and six views passed, for owner and unassigned clerk at 1440/390/320 pixels. Role guidance, exact entry destinations, control visibility and no horizontal overflow verified. Clerk phone screenshot visually inspected. Browser contexts are test-owned and disposed; user sessions untouched.
- PHP lint on templates/weekly-audits/index.php, JavaScript syntax and git diff --check passed.

Tests used only guarded synthetic HTTP port8099 with the synthetic response header, database port3307 / tivoli_auth_test with synthetic-auth-only-v1 marker. Existing fixture bootstrapping validates those before writes. UI browser checks use the generated synthetic fixture. Ordinary Joshua account permissions were not changed. No real credentials appear in tests; fixture identities remain ignored.

Manual next step: sign in as configured owner Eugene, open weekly-audits.php?page=duty, explicitly select Teye Joshua Amanor if he is the intended auditor, record Assign Audit Duty and its reason. Reopen as Joshua. Record a Needs Attention/Unsafe audit to create a follow-up; Eugene assigns staff/deadline; assignee marks done; Eugene verifies/closes or returns with a comment. Pass does not create work. Existing safety gates remain authoritative.

Recovery: revert only this explanatory card/shortcuts if needed, preserving the previous shared modal fix and all records. No data restore required.
