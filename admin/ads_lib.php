<?php
declare(strict_types=1);

/**
 * Ad image upload helpers (admin side).
 *
 * Uploaded files live OUTSIDE the repo so they survive an install.sh re-clone:
 *   filesystem: /var/lib/psv-tracker/uploads/<random>.<ext>
 *   public URL: /uploads/<random>.<ext>   (nginx alias; ^~ so it's never run as PHP)
 * The DB stores the filename only; URLs are built from that exact stored name,
 * so the saved extension and the served URL can never disagree.
 */

const ADS_UPLOAD_DIR = '/var/lib/psv-tracker/uploads';
const ADS_UPLOAD_URL = '/uploads/';

const ADS_MAX_BYTES = 2 * 1024 * 1024;   // 2 MB
const ADS_MIN_W = 200;
const ADS_MIN_H = 40;
const ADS_MAX_W = 2000;
const ADS_MAX_H = 1200;

/** Public URL for a stored ad image filename. */
function ads_image_url(string $file): string
{
    return ADS_UPLOAD_URL . rawurlencode($file);
}

/**
 * Validate an uploaded image ($_FILES['image']) and move it into the uploads
 * dir. Returns the stored filename on success, or null with $error set.
 *
 * The extension comes from the *validated* image type via
 * image_type_to_extension() (e.g. ".jpeg"), never from the client filename,
 * and is exactly what ads_image_url() will later serve.
 */
function ads_store_uploaded_image(array $file, ?string &$error): ?string
{
    $error = null;

    if (!isset($file['error']) || is_array($file['error'])) {
        $error = 'Invalid upload.';
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Choose an image to upload.';
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'Image is too large (max 2 MB).';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed (code ' . (int) $file['error'] . ').';
        return null;
    }
    if (($file['size'] ?? 0) > ADS_MAX_BYTES) {
        $error = 'Image is too large (max 2 MB).';
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'Invalid upload.';
        return null;
    }

    // Real-image check (reads the header, not the extension).
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $error = 'That file is not a valid image.';
        return null;
    }
    [$w, $h] = $info;
    $type = $info[2]; // IMAGETYPE_*

    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($type, $allowed, true)) {
        $error = 'Unsupported image type — use JPEG, PNG, GIF or WebP.';
        return null;
    }
    if ($w < ADS_MIN_W || $h < ADS_MIN_H) {
        $error = 'Image is too small (minimum ' . ADS_MIN_W . '×' . ADS_MIN_H . ' px).';
        return null;
    }
    if ($w > ADS_MAX_W || $h > ADS_MAX_H) {
        $error = 'Image is too large in dimensions (maximum ' . ADS_MAX_W . '×' . ADS_MAX_H . ' px).';
        return null;
    }

    $ext  = image_type_to_extension($type);          // includes the dot, e.g. ".jpeg"
    $name = bin2hex(random_bytes(16)) . $ext;

    if (!is_dir(ADS_UPLOAD_DIR)) {
        $error = 'Upload directory is missing on the server (' . ADS_UPLOAD_DIR . ').';
        return null;
    }
    $dest = ADS_UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = 'Could not save the uploaded image.';
        return null;
    }
    @chmod($dest, 0644);

    return $name;
}

/** Delete a stored ad image. basename() guards against path traversal. */
function ads_delete_image(?string $file): void
{
    if ($file === null || $file === '') {
        return;
    }
    $name = basename($file);
    $path = ADS_UPLOAD_DIR . '/' . $name;
    if (is_file($path)) {
        @unlink($path);
    }
}
