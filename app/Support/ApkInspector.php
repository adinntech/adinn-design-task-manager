<?php

namespace App\Support;

use ZipArchive;

/** Just enough APK knowledge to refuse a wrong file before it is published. */
final class ApkInspector
{
    /** An APK is a zip archive that holds an AndroidManifest.xml. */
    public static function isApk(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }

        $found = $zip->locateName('AndroidManifest.xml') !== false;
        $zip->close();

        return $found;
    }
}
