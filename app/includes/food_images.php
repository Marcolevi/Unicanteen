<?php

function foodImageRelativePath(?string $image): ?string
{
    $image = str_replace('\\', '/', trim((string) $image));

    if (!preg_match('#^uploads/foods/([A-Za-z0-9_-]+\.(?:jpe?g|png|gif|webp))$#i', $image)) {
        return null;
    }

    return $image;
}

function foodImageFilesystemPath(?string $image): ?string
{
    $relativePath = foodImageRelativePath($image);
    return $relativePath === null ? null : dirname(__DIR__, 2) . '/' . $relativePath;
}

function foodImageUrl(?string $image): ?string
{
    $relativePath = foodImageRelativePath($image);
    $filesystemPath = foodImageFilesystemPath($relativePath);

    if ($relativePath === null || $filesystemPath === null || !is_file($filesystemPath)) {
        return null;
    }

    return '/Ordering/' . implode('/', array_map('rawurlencode', explode('/', $relativePath)));
}

function foodImageDisplayUrl(?string $image): string
{
    return foodImageUrl($image) ?? '/Ordering/assets/images/food-fallback.svg';
}
