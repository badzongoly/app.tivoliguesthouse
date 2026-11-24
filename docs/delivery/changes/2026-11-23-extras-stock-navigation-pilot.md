# Extras & Stock navigation pilot

Date — 23 November 2026

Extras & Stock uses a shared muted Tivoli tab strip with a pale selected surface. Workflow shortcuts sit below it as text links. Catalogue actions stay near the catalogue heading.

At widths of 680 CSS pixels and below, the current tab remains visible beside More. The menu reveals available destinations, closes on Escape or an outside click, and restores focus to More on Escape. The shared navigation uses a single set of links. When JavaScript is unavailable, the links remain visible.

The pages use their original routes and permission checks, including owner-only Stock Costing. The navigation introduces no database, transaction or approval changes. Other workspaces keep their existing navigation. A compatible source rollback restores the previous appearance.

Verification used the marked synthetic server. Browser assertions passed across 18 owner and clerk views at 320, 390 and 1440 CSS pixels, including menu expansion and Escape focus. All 87 navigation HTTP checks passed. PHP and JavaScript syntax checks and Git whitespace checks passed.
