# F072: registration validation feedback

Escape the hyphen in the HTML username pattern so modern browsers enforce it. Report field and duplicate-account errors separately, retain non-password form values and handle insert failures without exposing database errors.

The repository retains its existing three-company-domain policy. deployment/20261007-launch/register.php keeps the live @stratastaff.com-only policy. Neither approval rules nor password limits were broadened.

Validation during implementation: 29 isolated database checks and 12 browser constraint checks passed. Valid live registration, duplicate rejection and Super Admin activation passed in final acceptance; the separate feedback-file upload is not yet verified live.
