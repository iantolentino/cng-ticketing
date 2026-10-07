# F069–F071: ticket workflow launch fixes

- F069: Full-edit validation read issue_escalator from an array that does not contain it. Remove that check and retain the stored creator identity.
- F070: Recovery called rowCount() on PDO instead of the executed statement. Read the statement row count inside the existing transaction.
- F071: The mixed native/external department sorter requires created_at and updated_at. Select and group those fields so native records are not displaced before the visible-row limit.

All three fixes were uploaded through cPanel and verified live with owned QA records: full edits, department routing, deletion, restoration and attachment retention passed. Existing staff records were preserved.
