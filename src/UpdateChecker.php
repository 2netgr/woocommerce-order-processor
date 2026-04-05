<?php

class UpdateChecker
{
    // GitHub repozitář ve formátu "owner/repo"
    const GITHUB_REPO    = 'Aramon/woocommerce-order-processor';
    const GITHUB_API_URL = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

    // Cache soubor — kontroluje max jednou za 24 hodin
    const CACHE_FILE     = 'data/update_check.json';
    const CACHE_HOURS    = 24;

    /**
     * Vrátí info o nové verzi nebo null pokud je vše aktuální.
     * Výsledek: ['version' => '2.1.1', 'url' => 'https://...', 'published' => '2024-...']
     */
    public static function check(): ?array
    {
        $cacheFile = __DIR__ . '/../' . self::CACHE_FILE;

        // Zkus cache
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && isset($cached['checked_at'])) {
                $age = time() - strtotime($cached['checked_at']);
                if ($age < self::CACHE_HOURS * 3600) {
                    return self::compareVersions($cached);
                }
            }
        }

        // Fetch z GitHub API
        $data = self::fetchGitHub();
        if (!$data) return null;

        // Ulož do cache
        $data['checked_at'] = date('Y-m-d H:i:s');
        @file_put_contents($cacheFile, json_encode($data));

        return self::compareVersions($data);
    }

    private static function fetchGitHub(): ?array
    {
        if (!function_exists('curl_init')) return null;

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => self::GITHUB_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'WooDashboard/' . APP_VERSION . ' (PHP)',
            CURLOPT_HTTPHEADER     => array('Accept: application/vnd.github+json'),
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) return null;

        $json = json_decode($response, true);
        if (!$json || empty($json['tag_name'])) return null;

        // tag_name může být "v2.1.1" nebo "2.1.1"
        $version = ltrim($json['tag_name'], 'v');

        return array(
            'version'      => $version,
            'tag'          => $json['tag_name'],
            'url'          => $json['html_url'] ?? ('https://github.com/' . self::GITHUB_REPO . '/releases'),
            'published_at' => $json['published_at'] ?? '',
            'body'         => $json['body'] ?? '', // changelog z release notes
        );
    }

    private static function compareVersions(array $data): ?array
    {
        if (empty($data['version'])) return null;

        // Porovnej verze (semver)
        if (version_compare($data['version'], APP_VERSION, '>')) {
            return array(
                'version'   => $data['version'],
                'url'       => $data['url'],
                'published' => isset($data['published_at']) ? substr($data['published_at'], 0, 10) : '',
                'body'      => $data['body'] ?? '',
            );
        }

        return null; // Jsme aktuální
    }

    /**
     * Vymaže cache — vynutí kontrolu při příštím načtení
     */
    public static function clearCache(): void
    {
        $cacheFile = __DIR__ . '/../' . self::CACHE_FILE;
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}
