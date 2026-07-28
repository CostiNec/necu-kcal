const supportedImageTypes = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
]);

type ImageOptimizationOptions = {
    targetBytes: number;
    maximumDimension: number;
};

const loadImage = (file: File): Promise<HTMLImageElement> =>
    new Promise((resolve, reject) => {
        const image = new Image();
        const url = URL.createObjectURL(file);

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('The image could not be decoded.'));
        };
        image.src = url;
    });

const canvasBlob = (
    canvas: HTMLCanvasElement,
    quality: number,
): Promise<Blob> =>
    new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) =>
                blob
                    ? resolve(blob)
                    : reject(new Error('The image could not be encoded.')),
            'image/jpeg',
            quality,
        );
    });

const optimizedImageName = (name: string): string => {
    const basename = name.replace(/\.[^.]+$/, '').trim();

    return `${basename || 'food-photo'}.jpg`;
};

export const optimizeImageForUpload = async (
    file: File,
    { targetBytes, maximumDimension }: ImageOptimizationOptions,
): Promise<File> => {
    if (!file.type.startsWith('image/')) {
        throw new Error('Unsupported image type.');
    }

    if (supportedImageTypes.has(file.type) && file.size <= targetBytes) {
        return file;
    }

    const image = await loadImage(file);
    const longestSide = Math.max(image.naturalWidth, image.naturalHeight);

    if (longestSide <= 0) {
        throw new Error('The image has invalid dimensions.');
    }

    let scale = Math.min(1, maximumDimension / longestSide);
    let quality = 0.88;

    for (let attempt = 0; attempt < 8; attempt += 1) {
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Image processing is unavailable.');
        }

        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        const blob = await canvasBlob(canvas, quality);

        if (blob.size <= targetBytes) {
            return new File([blob], optimizedImageName(file.name), {
                type: 'image/jpeg',
                lastModified: file.lastModified,
            });
        }

        if (quality > 0.58) {
            quality -= 0.1;
        } else {
            scale *= 0.8;
            quality = 0.82;
        }
    }

    throw new Error('The optimized image is still too large.');
};
