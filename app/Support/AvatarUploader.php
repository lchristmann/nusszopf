<?php

namespace App\Support;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Manual avatar upload (docs/rewrite/master-roadmap.md, "Slice 8"; historical
 * `AvatarDialog.js` + `pages/api/upload.js`). The historical flow trusted the
 * browser's own crop/compress step (`react-easy-crop` + `compressorjs`) and
 * only capped the upload at 1 MB via the S3 presigned-post's own
 * `content-length-range` condition — nothing re-decoded or re-encoded the
 * bytes server-side. This class does not extend that trust (`.claude/rules
 * /05-engineering-quality.md`): every upload is decoded with GD, center-
 * cropped to a square and re-encoded as a fresh JPEG, so a request built by
 * hand rather than through the real crop dialog can carry neither an
 * oversized/non-square image nor a file that merely *claims* to be a JPEG.
 *
 * Storage is the local `public` disk (register B4 — no object storage in
 * v1), one file per version (`avatars/{user}-v{n}.jpg`, the same historical
 * `{id}|nz_v{n}.jpeg` versioning scheme, `docs/rewrite/master-roadmap.md`).
 * The previous version is deleted only after the new file is written
 * successfully, so a failed upload never leaves a user without any avatar
 * file on disk.
 */
final class AvatarUploader
{
    public const DISK = 'public';

    /** Side length (px) a stored avatar is capped to. The client already crops to 150×150. */
    public const MAX_DIMENSION = 512;

    /**
     * Side length (px) an upload may declare at most, read from its header before anything is decoded
     * (P-4, SEC-05). GD allocates the full bitmap on decode: a 400 KB PNG that declares 20000×20000 would need
     * 1.6 GB and end the PHP process with a fatal error. The crop dialog uploads 512×512.
     */
    public const MAX_SOURCE_DIMENSION = 4096;

    public static function store(User $user, UploadedFile $file): string
    {
        $contents = $file->get();
        $size = $contents === false ? false : @getimagesizefromstring($contents);

        if ($size === false || $size[0] > self::MAX_SOURCE_DIMENSION || $size[1] > self::MAX_SOURCE_DIMENSION) {
            throw new RuntimeException('Die Datei ist kein gültiges Bild.');
        }

        $decoded = @imagecreatefromstring($contents);

        if (! $decoded instanceof GdImage) {
            throw new RuntimeException('Die Datei ist kein gültiges Bild.');
        }

        $square = self::centerSquareCrop($decoded);
        imagedestroy($decoded);

        $version = $user->avatar_version + 1;
        $path = "avatars/{$user->id}-v{$version}.jpg";
        $disk = Storage::disk(self::DISK);

        $encoded = self::encodeJpeg($square);
        imagedestroy($square);

        $disk->put($path, $encoded);

        self::deleteStoredAvatar($user);

        $user->forceFill(['picture' => $path, 'avatar_version' => $version])->save();

        return $path;
    }

    /**
     * Deletes the current avatar file, if any — the same cleanup the
     * historical `clean_up_users_digitalocean`/`clean_up_deleted_user`
     * triggers performed (`docs/domain/workflows.md`, "profile picture
     * replacement"/"account deletion"). A no-op for a Google-provided URL:
     * there is nothing on this instance's disk to remove.
     */
    public static function deleteStoredAvatar(User $user): void
    {
        $current = $user->picture;

        if (filled($current) && ! Str::startsWith($current, ['http://', 'https://'])) {
            Storage::disk(self::DISK)->delete($current);
        }
    }

    private static function centerSquareCrop(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $srcX = intdiv($width - $side, 2);
        $srcY = intdiv($height - $side, 2);

        $target = min($side, self::MAX_DIMENSION);
        $canvas = imagecreatetruecolor($target, $target);
        imagecopyresampled($canvas, $image, 0, 0, $srcX, $srcY, $target, $target, $side, $side);

        return $canvas;
    }

    private static function encodeJpeg(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, quality: 85);

        return ob_get_clean();
    }
}
