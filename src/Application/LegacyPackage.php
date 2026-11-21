<?php
declare(strict_types=1);

namespace App\Application;

/** Explicit 0.4 transport contract: temporary entry points are removed after MVC installation. */
final class LegacyPackage
{
    /** Files demanded by the published 0.4 updater, not an archived copy of its implementation. */
    public static function required(): array
    {
        return [
            'src/VERSION', 'src/Updater.php', 'src/Updates.php', 'src/bootstrap.php',
            'src/Domain.php', 'src/Store.php', 'src/Migrations.php', 'src/migration_catalog.php',
            'src/circuit_profiles.php', 'src/InstallationState.php', 'src/ApacheProtection.php',
            'src/Translation.php', 'src/Header.php', 'src/locale_es.php',
            'public_html/index.php', 'public_html/api.php', 'public_html/install.php',
            'public_html/update.php', 'public_html/options.php', 'public_html/database.php',
            'public_html/i18n.php', 'data/parameters.json', 'data/symptoms.json',
            'public_html/assets/app.js', 'public_html/assets/app.css', 'public_html/assets/navigation.js',
        ];
    }

    /** Delete only known application files, never private state or arbitrary files in a directory. */
    public static function retired(): array
    {
        return array_merge(self::required(), [
            'src/MvcTransition.php', 'public_html/faq.php',
            'data/migrations/001-game-catalog.data.sql', 'data/migrations/001-game-catalog.schema.sql',
            'data/migrations/002-circuit-presets.data.sql', 'data/migrations/002-circuit-presets.schema.sql',
        ]);
    }

    /** Read a bounded data envelope; PHP inside the transport is never evaluated by an MVC installer. */
    public static function embedded(string $archive): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new \RuntimeException('Cannot open update container.');
        }
        try {
            $entry = $zip->statName('manifest.json');
            if (!$entry || $entry['size'] > 131072 || $zip->numFiles > 500) {
                throw new \RuntimeException('Invalid update container bounds.');
            }
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true, 64, JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? '') === 'f125-update-v3') {
                return null;
            }
            if (($manifest['format'] ?? '') !== 'f125-update-v2' || ($manifest['transition'] ?? '') !== 'f125-mvc-1' || $zip->numFiles > 150) {
                throw new \RuntimeException('Unsupported update container.');
            }
            $entry = $zip->statName('data/parameters.json');
            if (!$entry || $entry['size'] > 2097152) {
                throw new \RuntimeException('Invalid transition envelope.');
            }
            $json = $zip->getFromName('data/parameters.json');
            if (!hash_equals($manifest['files']['data/parameters.json'] ?? '', hash('sha256', $json))) {
                throw new \RuntimeException('Transition envelope digest mismatch.');
            }
            return self::decode(json_decode($json, true, 64, JSON_THROW_ON_ERROR)['mvcPackage'] ?? null);
        } finally {
            $zip->close();
        }
    }

    /** The inner ZIP has an independent digest and the same verified release identity as its container. */
    public static function decode(mixed $envelope): string
    {
        if (!is_array($envelope) || !is_string($envelope['archive'] ?? null) || strlen($envelope['archive']) > 2000000 || !preg_match('/^[a-f0-9]{64}$/D', $envelope['sha256'] ?? '')) {
            throw new \RuntimeException('Invalid embedded MVC package.');
        }
        $bytes = base64_decode($envelope['archive'], true);
        if (!is_string($bytes) || !hash_equals($envelope['sha256'], hash('sha256', $bytes))) {
            throw new \RuntimeException('Embedded MVC package digest mismatch.');
        }
        return $bytes;
    }
}
