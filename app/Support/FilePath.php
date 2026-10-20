<?php

namespace App\Support;

class FilePath
{
    public static function absolute(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
