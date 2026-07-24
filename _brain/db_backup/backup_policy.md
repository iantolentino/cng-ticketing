# DATABASE BACKUP POLICY

> OPTIONAL MODULE — only generate/fill this in if the project has a database.
> See `claude.md` § OPTIONAL MODULE RULE.

---

## Backup Strategy
[Automated snapshot / logical dump (pg_dump, mysqldump) / managed provider backup (RDS, PlanetScale, etc.)]

## Schedule
| Environment | Frequency | Retention |
|-------------|-----------|-----------|
| Production  |           |           |
| Staging     |           |           |

## Storage Location
[Where backups live — S3 bucket, provider-managed, local encrypted volume, etc.]

## Restore Procedure
1. [Step 1]
2. [Step 2]
3. [Verify data integrity post-restore]

## Restore Test Cadence
[How often a restore is actually tested — untested backups are not a backup strategy]

## Rules
- Never store backups unencrypted in a public or shared location
- Never commit backup files to version control
- Backup credentials follow `security/secrets_policy.md`
