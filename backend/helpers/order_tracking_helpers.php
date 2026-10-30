<?php

/**
 * Shared helpers for the order tracking page and API.
 */

function order_tracking_safe_image_src(string $value): string
{
    $raw = trim($value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $raw) === 1) {
        return '';
    }

    if (preg_match('/^(?:javascript|vbscript):/i', $raw) === 1) {
        return '';
    }

    if (preg_match('/^data:/i', $raw) === 1) {
        return preg_match('/^data:image\/(?:png|jpe?g|webp|gif);base64,[a-z0-9+\/=\s]+$/i', $raw) === 1
            ? $raw
            : '';
    }

    if (
        preg_match('/^(?:https?:)?\/\//i', $raw) === 1
        || str_starts_with($raw, '/')
        || str_starts_with($raw, './')
        || str_starts_with($raw, '../')
    ) {
        return $raw;
    }

    if (preg_match('/^[a-z0-9][a-z0-9._\/-]*$/i', $raw) === 1) {
        return $raw;
    }

    return '';
}
