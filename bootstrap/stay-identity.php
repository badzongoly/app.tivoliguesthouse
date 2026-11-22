<?php
require_once __DIR__.'/hr.php';

/**
 * Prepare an optional guest ID for the caller's check-in transaction.
 * No guest bytes, filenames or document numbers enter general audit details.
 * The returned envelope must only be passed internally to StayService.
 */
function stay_prepare_identity(int $actor, array $file): array
{
    $access = access_service();
    $access->requirePrivilege($actor, 'bookings.manage');
    $error = $file['error'] ?? null;
    if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The ID document did not upload successfully. Choose the file again or continue without it.');
    }
    $temporary = $file['tmp_name'] ?? null;
    $name = $file['name'] ?? null;
    if (!is_string($temporary) || !is_string($name) || !is_uploaded_file($temporary)) {
        throw new RuntimeException('Choose a valid uploaded ID document.');
    }
    $limit = 5 * 1024 * 1024;
    $bytes = file_get_contents($temporary, false, null, 0, $limit + 1);
    if ($bytes === false || strlen($bytes) < 1 || strlen($bytes) > $limit) {
        throw new RuntimeException('ID documents must be non-empty PDF, JPEG or PNG files up to 5 MB.');
    }
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    $allowed = ['pdf'=>'application/pdf', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'png'=>'image/png'];
    if (!isset($allowed[$extension]) || $mime !== $allowed[$extension]) {
        throw new RuntimeException('The ID document content must match a PDF, JPEG or PNG filename.');
    }
    if ($mime !== 'application/pdf') {
        $image = @getimagesizefromstring($bytes);
        if ($image === false || ($image['mime'] ?? '') !== $mime) {
            throw new RuntimeException('The ID image could not be validated.');
        }
    } elseif (!str_starts_with($bytes, '%PDF-')) {
        throw new RuntimeException('The ID PDF could not be validated.');
    }
    $vault = new \Tivoli\Infrastructure\HrVault(hr_config()['hr_key']);
    return [
        'cipher'=>$vault->seal($bytes, 'guest-id:'.$actor),
        'mime'=>$mime,
        'size'=>strlen($bytes),
        'sha'=>hash('sha256', $bytes),
        'owner'=>$actor,
    ];
}

/** Return bytes only after current authority and durable access audit succeed. */
function stay_download_identity(int $actor, int $bookingId): array
{
    $access = access_service();
    try {
        return $access->transaction(function () use ($access, $actor, $bookingId): array {
            $access->requirePrivilege($actor, 'bookings.manage');
            $row = $access->repo->run(
                'SELECT i.booking_id,i.cipher,i.mime,i.size,i.sha,i.owner,b.status FROM stay_identity i JOIN bookings b ON b.id=i.booking_id WHERE i.booking_id=? FOR UPDATE',
                [$bookingId]
            )->fetch();
            if($row)$row=(new \Tivoli\Service\StayService($access))->displayDeparture($row+['id'=>$bookingId]);
            if (!$row || (!$access->repo->isAdmin($actor) && $row['status'] !== 'active')) {
                throw new \Tivoli\Security\PermissionDenied('This guest identity document is unavailable to your account.');
            }
            $extensions = ['application/pdf'=>'pdf', 'image/jpeg'=>'jpg', 'image/png'=>'png'];
            if (!isset($extensions[$row['mime']]) || (int)$row['size'] < 1 || (int)$row['size'] > 5 * 1024 * 1024) {
                throw new RuntimeException('Guest identity document integrity check failed.');
            }
            $access->audit($actor, 'stay.identity_downloaded', 'booking', (string)$bookingId, 'succeeded');
            $vault = new \Tivoli\Infrastructure\HrVault(hr_config()['hr_key']);
            $bytes = $vault->open($row['cipher'], 'guest-id:'.(int)$row['owner']);
            if (strlen($bytes) !== (int)$row['size'] || !hash_equals($row['sha'], hash('sha256', $bytes))) {
                throw new RuntimeException('Guest identity document integrity check failed.');
            }
            return ['bytes'=>$bytes, 'mime'=>$row['mime'], 'filename'=>'guest-id-'.$bookingId.'.'.$extensions[$row['mime']]];
        });
    } catch (Throwable $error) {
        $access->audit($actor, 'stay.identity_download_failed', 'booking', (string)$bookingId, 'denied', ['reason'=>'permission_missing_or_storage_integrity_failure']);
        throw $error;
    }
}
