#!/usr/bin/env python3
import json
import os
import sys
from PIL import Image, ImageOps
from pillow_heif import register_heif_opener


def resize_to_max(image, max_size):
    width, height = image.size
    if width <= max_size and height <= max_size:
        return image.copy()
    copy = image.copy()
    copy.thumbnail((max_size, max_size), Image.Resampling.LANCZOS)
    return copy


def main():
    if len(sys.argv) != 8:
        raise SystemExit('usage: heic-convert.py <origin> <compressed> <thumbnail> <compressed_max> <thumbnail_size> <compressed_quality> <thumbnail_quality>')

    origin_path = sys.argv[1]
    compressed_path = sys.argv[2]
    thumbnail_path = sys.argv[3]
    compressed_max = int(sys.argv[4])
    thumbnail_size = int(sys.argv[5])
    compressed_quality = int(sys.argv[6])
    thumbnail_quality = int(sys.argv[7])

    register_heif_opener()
    image = Image.open(origin_path)
    image = ImageOps.exif_transpose(image).convert('RGB')
    width, height = image.size

    os.makedirs(os.path.dirname(compressed_path), exist_ok=True)
    os.makedirs(os.path.dirname(thumbnail_path), exist_ok=True)

    compressed = resize_to_max(image, compressed_max)
    compressed.save(compressed_path, 'JPEG', quality=compressed_quality, optimize=True)

    thumbnail = resize_to_max(image, thumbnail_size)
    thumbnail.save(thumbnail_path, 'JPEG', quality=thumbnail_quality, optimize=True)

    print(json.dumps({
        'width': width,
        'height': height,
        'compressed_path': compressed_path,
        'thumbnail_path': thumbnail_path,
    }))


if __name__ == '__main__':
    main()
