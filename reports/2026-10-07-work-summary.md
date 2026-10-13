# CNG Ticketing System — work summary, 7 October 2026

The live ticketing workflows were repaired, checked after cPanel upload and retested with four accounts. Final live acceptance passed **141 functional/access checks and 16 responsive layout cases**. Existing staff tickets and real account credentials/permissions were preserved.

Live system: https://cng-tickets.stratastaff.com/login.php

## Improvements and fixes

| Work | Result | Deployment status |
| --- | --- | --- |
| Department workload cards | Align department names and involved/current/overdue figures; wrap long names; use three desktop, two tablet and one mobile column. | Uploaded and verified live in light/dark themes at eight widths. |
| Full ticket editing | Remove validation of a missing issue_escalator array value while retaining the stored escalator identity. | Uploaded; Super Admin, POD Leader and Management edits save successfully. |
| Ticket recovery | Read rowCount() from the executed restore statement instead of PDO. | Uploaded; deletion/restoration retains details, status, comments and attachments. |
| Native department involvement | Include creation/update timestamps required by the mixed-source sorter. | Uploaded; new native tickets appear and department-routing changes persist. |
| Registration feedback | Fix browser username pattern, give specific field/duplicate errors, preserve non-password inputs and handle database failures safely. | Implemented and tested locally; separate feedback-file deployment remains unverified. Valid live registration/approval already passes. |
| Pending activation access | Restrict pending/unapproved registrations and approval actions to Super Admin. Block direct edit/save/delete/permission/reset attempts by Management. | Uploaded and verified live with the Management account. Approved-user access remains available. |

## Account and workflow checks

The tested accounts were Super Admin, CNG Admin, POD Leader and the newly supplied Management account. Credentials and identifying staff details are omitted from this public report.

- Confirmed login, expected module access and access denials for all four accounts.
- Verified ticket creation where permitted, full edits, comments, workflow changes, department routing, attachment access, filtered CSV/XLSX exports and delete/restore behavior.
- Verified registration, duplicate rejection, pending-login rejection, Super Admin approval and login after activation.
- Confirmed Management cannot see pending registrations or bypass the restriction with valid-CSRF direct requests. Newly approved accounts become visible to Management.
- Management currently permits ticket edits, comments, status updates, exports and permitted attachment downloads. Ticket creation/deletion/recovery and Super Admin pages remain disabled under its existing permissions.
- Confirmed CNG Admin and POD restrictions remain intact, including their disabled creation/deletion/recovery actions and POD export/private-attachment restrictions.
- Confirmed responsive department cards have no clipped content or page overflow from 320 to 1920 pixels, in both themes.

The 141 final checks comprise 114 workflow/module checks and 27 registration/approval checks; 53 module-route checks are included in that total. The 16 layout cases are counted separately. No browser script exceptions occurred in the final main workflow run. Earlier local validation also passed 209 regression checks, 11 ticket-fix database checks, 29 registration database checks, 12 registration browser checks and 17 isolated approval-boundary checks. These are separate suites, not an aggregate number of unique tests.

Before publication, the isolated GitHub checkout passed all 174 regression checks available on its baseline, all ten changed PHP-file syntax checks and the whitespace/diff check. The local suite's 209 count includes older, unrelated local work that is not part of this update.

## Cleanup and rollout preparation

Only clearly labelled QA records were created or changed during live testing. Own test accounts, calendar entries, attendance entries and holidays were removed through normal application actions. Own tickets were soft-deleted; normal recovery/audit traces remain. Deleted test-ticket and attachment URLs were checked for denied access. No existing TL tickets or real account passwords/permissions were changed.

Prepared cPanel upload instructions and focused packages, then checked the live fixes after the user uploaded them. Prepared email and Teams announcement wording with the login link, confirmation that CNG Team Leaders already have individual accounts, reference to the CNG Admin User Guide attachment, and directions to contact the administrator via Teams or email for questions or account/access changes. Users can change their password through Settings.

## Readiness and remaining scope

The **tested ticketing workflows are ready for general-use announcement**. This is not a certification of every feature: actual leave approvals, real Recruitment writes, email inbox delivery, backup recovery and load/concurrency testing were outside the final acceptance run. Real account passwords were not changed during the final checks.

The registration feedback improvement remains a separate deployment item. The repository retains its pre-existing three-company-domain policy. The production variant at deployment/20261007-launch/register.php keeps the live @stratastaff.com-only policy; it does not broaden registration eligibility.

## GitHub update scope

This update contains today's source fixes, CSS cache change, adjusted registration regression assertions, production registration variant, fix-memory entries and this summary. Older unrelated local changes remain outside this update. Credentials, company staff files, database contents, attachments, authenticated HTML exports, screenshots and temporary test databases are not published.

GitHub source publication is separate from cPanel deployment. Use the prepared live-based upload packages when updating production; the local application has other changes that were not synchronized as part of today's commit.
