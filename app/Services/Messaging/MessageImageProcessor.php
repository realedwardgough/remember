<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Imagick;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Throwable;

class MessageImageProcessor
{
    public function process(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $mime = $file->getMimeType();
        $heic = in_array($mime, ['image/heic', 'image/heif'], true);
        $error = 'Choose a valid JPEG, PNG, WebP, GIF, or AVIF photo. HEIC photos require server support; you can export them as JPEG.';
        if (! $file->isValid() || $file->getSize() > MessageImageSettings::MAX_UPLOAD_BYTES
            || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/heic', 'image/heif'], true)) {
            throw ValidationException::withMessages(['image' => $error]);
        }

        try {
            if ($heic) {
                if (! class_exists(Imagick::class) || Imagick::queryFormats('HEIC') === []) {
                    throw ValidationException::withMessages(['image' => $error]);
                }
                $probe = new Imagick;
                $probe->pingImage($path);
                [$width, $height] = [$probe->getImageWidth(), $probe->getImageHeight()];
                $probe->clear();
            } else {
                $dimensions = @getimagesize($path);
                [$width, $height] = $dimensions === false ? [0, 0] : [$dimensions[0], $dimensions[1]];
            }
            if ($width < 1 || $height < 1 || $width > MessageImageSettings::MAX_EDGE || $height > MessageImageSettings::MAX_EDGE || $width * $height > MessageImageSettings::MAX_PIXELS) {
                throw ValidationException::withMessages(['image' => 'This photo is too large. Choose an image up to 20 megapixels and 8192 pixels on either edge.']);
            }
            $limit = ini_parse_quantity((string) ini_get('memory_limit'));
            if ($limit > 0 && $width * $height * 12 + $file->getSize() * 2 > $limit - memory_get_usage(true)) {
                throw ValidationException::withMessages(['image' => 'This photo needs too much memory to process. Please choose a smaller copy.']);
            }

            $manager = new ImageManager($heic ? ImagickDriver::class : GdDriver::class, autoOrientation: true, decodeAnimation: false, strip: true);
            $image = $manager->decodePath($path)->scaleDown(width: 2048, height: 2048);
            $width = $image->width();
            $height = $image->height();
            $full = $image->encodeUsingFormat(Format::WEBP, quality: 82)->toString();
            $thumbnail = $image->scaleDown(width: 480, height: 480)->encodeUsingFormat(Format::WEBP, quality: 75)->toString();

            return ['full' => $full, 'thumbnail' => $thumbnail, 'width' => $width, 'height' => $height];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['image' => $error]);
        }
    }
}
