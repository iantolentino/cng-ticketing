# Registration feedback production variant

This register.php includes the 7 October validation/feedback improvements and preserves the live site's @stratastaff.com-only registration policy. The root register.php retains the repository's existing three-domain policy.

To deploy this separate registration improvement, back up the live register.php and upload this file beside login.php/index.php in the cng-tickets.stratastaff.com document root. No SQL, configuration, CSS or JavaScript changes are needed. Then verify field feedback, username validation, valid pending registration and Super Admin approval using a labelled QA account, and remove that account afterward.

The feedback variant's live deployment has not yet been verified. The ticket-edit/recovery/department and Super Admin-only pending-activation changes were separately uploaded and verified live.
