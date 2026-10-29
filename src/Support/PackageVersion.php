<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Support;

use Composer\InstalledVersions;
use Throwable;

/**
 * This package's installed version, folded into the export fingerprint so an upgrade invalidates the last export.
 */
final class PackageVersion
{
    public const string PACKAGE = 'abetwothree/lang-export';

    /**
     * Returns the installed version, else its commit reference, else `dev`.
     */
    public static function current(): string
    {
        try {
            if (InstalledVersions::isInstalled(self::PACKAGE)) {
                return InstalledVersions::getPrettyVersion(self::PACKAGE)
                    ?? InstalledVersions::getReference(self::PACKAGE)
                    ?? 'dev';
            }
        } catch (Throwable) {
            // Composer's runtime API is unavailable outside a Composer install.
        }

        return 'dev';
    }
}
