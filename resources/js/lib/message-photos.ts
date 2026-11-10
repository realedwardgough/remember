const maxOriginalBytes = 50 * 1024 * 1024;
const targetBytes = 768 * 1024;
const maxEdge = 2048;
const supportedTypes = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/avif',
    'image/heic',
    'image/heif',
]);

export async function prepareMessagePhoto(
    file: File,
    maxUploadBytes = 10 * 1024 * 1024,
): Promise<File> {
    if (file.size > maxOriginalBytes) {
        throw new Error('Choose a photo smaller than 50 MB.');
    }

    if (
        !supportedTypes.has(file.type) &&
        !(
            file.type === '' &&
            /\.(jpe?g|png|webp|gif|avif|heic|heif)$/i.test(file.name)
        )
    ) {
        throw new Error('Choose a JPEG, PNG, WebP, GIF, AVIF, or HEIC photo.');
    }

    const url = URL.createObjectURL(file);
    const image = new Image();

    try {
        image.src = url;

        try {
            await image.decode();
        } catch {
            // Unsupported HEIC can still be handled by the server when it fits.
            if (file.size <= maxUploadBytes) {
                return file;
            }

            throw new Error(
                'This browser couldn’t resize this photo. Export it as JPEG or choose a smaller copy.',
            );
        }

        const limit = Math.min(targetBytes, maxUploadBytes - 16 * 1024);
        const ratio = Math.min(
            1,
            maxEdge / Math.max(image.naturalWidth, image.naturalHeight),
        );
        let width = Math.max(1, Math.round(image.naturalWidth * ratio));
        let height = Math.max(1, Math.round(image.naturalHeight * ratio));
        const canvas = document.createElement('canvas');

        do {
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');

            if (!context) {
                throw new Error(
                    'This browser couldn’t resize this photo. Please choose a smaller copy.',
                );
            }

            context.drawImage(image, 0, 0, width, height);

            for (const quality of [0.82, 0.7, 0.58]) {
                let blob = await new Promise<Blob | null>((resolve) =>
                    canvas.toBlob(resolve, 'image/webp', quality),
                );

                if (blob && blob.type !== 'image/webp') {
                    // Older browsers may only encode PNG/JPEG; keep transparency white.
                    context.globalCompositeOperation = 'destination-over';
                    context.fillStyle = '#fff';
                    context.fillRect(0, 0, width, height);
                    context.globalCompositeOperation = 'source-over';
                    blob = await new Promise<Blob | null>((resolve) =>
                        canvas.toBlob(resolve, 'image/jpeg', quality),
                    );
                }

                if (blob && blob.size <= limit) {
                    const extension =
                        blob.type === 'image/webp' ? 'webp' : 'jpg';

                    return new File(
                        [blob],
                        `${file.name.replace(/\.[^.]+$/, '')}.${extension}`,
                        { type: blob.type, lastModified: file.lastModified },
                    );
                }
            }

            width = Math.round(width * 0.75);
            height = Math.max(1, Math.round(height * 0.75));
        } while (Math.max(width, height) >= 256);

        // Tiny originals do not need resizing, and do not require canvas support.
        if (file.size <= limit) {
            return file;
        }

        throw new Error(
            'This photo couldn’t be made small enough to upload. Please choose a smaller copy.',
        );
    } finally {
        image.src = '';
        URL.revokeObjectURL(url);
    }
}
