# GitHub publication and private data

The owner explicitly approved merging the reviewed source into main and making the repository public on 22 November 2026. Public visibility applies to the reviewed source code only; credentials and private business data must remain excluded. This source snapshot is not a production deployment or a backup of business data.

Do not commit local configuration, database dumps, guest/staff records, document uploads, mail previews, runtime journals, logs, private evidence or original internal operating documents. Local files remain on disk and retain their existing access controls. Git ignore rules are an additional guard; inspect the actual staged snapshot and complete outgoing Git history before every push.

Configuration examples contain placeholders only. Generate fresh secrets in the private local configuration. Obtain approved internal SOP documents separately through the owner's controlled channel; they are intentionally not distributed with the source repository. The public property logo/artwork and website business contact information are application assets, not guest or employee records.

Publication checks include an official Gitleaks scan of the exact staged source snapshot and all outgoing history, a local exact-value check against protected configuration, forbidden-path checks, syntax validation and selected existing tests. Scan reports contain only redacted findings and remain outside the repository. Synthetic test credentials are fictional and confined to the guarded isolated environment; they do not authorize access to ordinary data.

Use a normal fast-forward push. Never force-push. Repository visibility changes require the owner's explicit authorization. Preserve every local database and financial record. GitHub publication does not activate external messaging, run migrations, change configuration secrets or authorize deployment.
