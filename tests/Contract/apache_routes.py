"""No PHP execution or database connection: root Apache public-route contract."""
from pathlib import Path
import re
root = Path(__file__).resolve().parents[2]
lines = (root / ".htaccess").read_text(encoding="utf-8").splitlines()
routes = [(line.split()[1], line.split()[2]) for line in lines if line.startswith("RewriteRule ")]
for name in ["purchases.php", "handover.php", "stock-counts.php", "housekeeping.php", "weekly-audits.php", "financial-corrections.php", "finance.php", "auth/login.php"]:
    matches = [(pattern, target) for pattern, target in routes if re.search(pattern, name)]
    assert matches and matches[0][1].startswith("public/"), "Missing public route: " + name
for name in [".env", "db.php", "src/Service/PurchaseService.php", "docs/PURCHASING.md", "tests/Contract/purchases_http.py", "purchases.php.bak", "unlisted.php"]:
    matches = [(pattern, target) for pattern, target in routes if re.search(pattern, name)]
    assert matches and matches[0][1] == "-", "Private path routed publicly: " + name
assert "Require local" in lines
assert "Options -Indexes" in lines
assert lines[-1] == "RewriteRule ^ - [F,END]"
print("PASS 8 public routes, 7 private paths, local-only access and deny-by-default")
