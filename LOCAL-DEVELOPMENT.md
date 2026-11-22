# Local development baseline

The initial source snapshot retains existing whitespace warnings reported by git diff --cached --check. No formatting sweep was performed.

Database credentials, SQL snapshots, logs and local setup backups are excluded from Git. Copy config/database.example.php to config/database.local.php and configure a separate local database to install this source. The checked-in .htaccess restricts access to local clients and is not a production deployment configuration.

The restored local database contains protected production snapshot data. Use a separate synthetic database for automated regression tests.