<?php
declare(strict_types=1);

/** Validate an optional creation attachment before opening a transaction. */
function validate_creation_attachment(?array $file, bool $allowed): ?array
{
    if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;
    if (!$allowed) throw new RuntimeException('You do not have permission to upload attachments.');
    if (in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new RuntimeException('Attachment exceeds the upload size limit (maximum 10 MB).');
    }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Attachment upload failed. Please choose the file again.');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1 || $size > 10 * 1024 * 1024) {
        throw new RuntimeException('Attachment must be nonempty and 10 MB or smaller.');
    }
    $name = basename(str_replace('\\', '/', (string) ($file['name'] ?? '')));
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    if (!$name || strlen($name) > 255) throw new RuntimeException('Choose a file with a name of 255 bytes or fewer.');
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$extension]) || $mime !== $types[$extension]) {
        throw new RuntimeException('Only PDF, JPG, and PNG attachments are allowed.');
    }
    return ['name' => $name, 'extension' => $extension, 'mime' => $mime, 'size' => $size, 'tmp_name' => $file['tmp_name']];
}

/** The caller owns the transaction and removes $savedPath if it rolls back. */
function save_creation_attachment(PDO $pdo, int $ticketId, int $userId, array $file, ?string &$savedPath): void
{
    $directory = ROOT_PATH . '/storage/private/ticket-attachments/' . $ticketId;
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Attachment directory could not be created.');
    }
    $storedName = bin2hex(random_bytes(16)) . '.' . $file['extension'];
    $relativePath = 'storage/private/ticket-attachments/' . $ticketId . '/' . $storedName;
    if (!move_uploaded_file($file['tmp_name'], ROOT_PATH . '/' . $relativePath)) {
        throw new RuntimeException('Attachment could not be saved.');
    }
    $savedPath = ROOT_PATH . '/' . $relativePath;
    $pdo->prepare('INSERT INTO ticket_attachments(ticket_id,uploaded_by,file_name,file_path,stored_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([$ticketId, $userId, $file['name'], $relativePath, $storedName, $file['name'], $file['mime'], $file['size']]);
    activity($ticketId, $userId, 'attachment_uploaded', ['file_name' => $file['name']]);
}
