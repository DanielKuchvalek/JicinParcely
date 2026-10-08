<?php

declare(strict_types=1);

namespace JicinParcely;

/**
 * GET požadavky na služby ČÚZK s JSON odpovědí a jednoduchou souborovou cache.
 */
final class Http
{
    public function __construct(
        private string $cacheDir,
        private int $timeout = 8,
    ) {
    }

    public static function create(): self
    {
        return new self(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jicin-parcely-cache');
    }

    /**
     * @param int $cacheTtl jak dlouho (s) držet odpověď v cache; 0 = necachovat
     * @throws ServiceUnavailable při chybě sítě, HTTP nebo chybové odpovědi ArcGIS
     */
    public function getJson(string $url, array $query = [], int $cacheTtl = 0): array
    {
        $fullUrl = $query === [] ? $url : $url . '?' . http_build_query($query);
        $cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . sha1($fullUrl) . '.json';

        if ($cacheTtl > 0 && is_file($cacheFile) && filemtime($cacheFile) > time() - $cacheTtl) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'JicinParcely/2.0',
            // Kontrola certifikátů je vypnutá kvůli XAMPPu bez balíčku certifikátů (viz README).
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new ServiceUnavailable("HTTP $status " . curl_error($ch) . " ($url)");
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new ServiceUnavailable("Neplatný JSON ($url)");
        }
        if (isset($data['error'])) {
            throw new ServiceUnavailable('Chyba ArcGIS: ' . json_encode($data['error'], JSON_UNESCAPED_UNICODE) . " ($url)");
        }

        if ($cacheTtl > 0 && self::isCacheable($data)) {
            $this->writeCache($cacheFile, $data);
        }

        return $data;
    }

    /**
     * Prázdný seznam prvků z ArcGIS může být jen výpadek služby; v cache by vydržel
     * celou dobu platnosti (u seznamu k.ú. týden), proto se neukládá.
     */
    public static function isCacheable(array $data): bool
    {
        return !(array_key_exists('features', $data) && $data['features'] === []);
    }

    private function writeCache(string $file, array $data): void
    {
        if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0775, true) && !is_dir($this->cacheDir)) {
            return;
        }

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $file);
        }
    }
}
