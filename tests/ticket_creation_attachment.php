<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/ticket_attachment.php';

$passed = 0;
function expect_attachment(bool $condition): void
{
    global $passed;
    if (!$condition) throw new RuntimeException('Attachment validation assertion failed.');
    $passed++;
}
expect_attachment(validate_creation_attachment(null, true) === null);
expect_attachment(validate_creation_attachment(null, false) === null);
expect_attachment(validate_creation_attachment(['error' => UPLOAD_ERR_NO_FILE], true) === null);
foreach ([
    [['error' => UPLOAD_ERR_OK], false, 'permission'],
    [['error' => UPLOAD_ERR_INI_SIZE], true, 'size limit'],
    [['error' => UPLOAD_ERR_FORM_SIZE], true, 'size limit'],
    [['error' => UPLOAD_ERR_PARTIAL], true, 'upload failed'],
    [['error' => UPLOAD_ERR_NO_TMP_DIR], true, 'upload failed'],
    [['error' => UPLOAD_ERR_CANT_WRITE], true, 'upload failed'],
    [['error' => UPLOAD_ERR_EXTENSION], true, 'upload failed'],
    [['error' => [UPLOAD_ERR_OK]], true, 'upload failed'],
    [['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__], true, 'upload failed'],
    [['error' => UPLOAD_ERR_OK, 'tmp_name' => []], true, 'upload failed'],
] as [$file, $allowed, $message]) {
    try {
        validate_creation_attachment($file, $allowed);
        throw new LogicException('Expected attachment rejection.');
    } catch (RuntimeException $exception) {
        expect_attachment(str_contains($exception->getMessage(), $message));
    }
}
echo "$passed attachment validation checks passed.\n";
