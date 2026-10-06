<?php

namespace App\Service\Scraping;

final class HtmlPageFetcher
{
    private const MAX_REDIRECTS = 3;
    private const MAX_BYTES = 1048576;
    private const TIMEOUT_SECONDS = 10;

    public function __construct(private readonly PublicUrlGuard $guard)
    {
    }

    /** @return array{url: string, html: string, bytes: int} */
    public function fetch(string $url): array
    {
        $current = $this->guard->normalize($url);
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $parts = parse_url($current);
            if (!is_array($parts) || !isset($parts['host'], $parts['scheme'])) {
                throw new \InvalidArgumentException('Informe uma URL HTTP ou HTTPS.');
            }
            $host = strtolower((string) $parts['host']);
            if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
                $host = substr($host, 1, -1);
            }
            $port = (int) ($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80));
            $ips = $this->guard->resolve($host);
            foreach ($ips as $ip) {
                $this->guard->assertPublicIp($ip);
            }
            $response = $this->request($current, $host, $port, $ips[0]);
            if ($response['location'] !== null) {
                if ($hop === self::MAX_REDIRECTS) {
                    throw new \InvalidArgumentException('A URL teve redirecionamentos demais.');
                }
                $current = $this->guard->normalize($this->resolveRedirect($current, $response['location']));
                continue;
            }
            if ($response['status'] < 200 || $response['status'] >= 300) {
                throw new \RuntimeException('A página não pôde ser lida.');
            }
            if (!$this->isHtml($response['type'])) {
                throw new \InvalidArgumentException('A página precisa ser HTML.');
            }
            $html = $response['body'];
            if ($html === '') {
                throw new \RuntimeException('A página não retornou conteúdo.');
            }

            return ['url' => $current, 'html' => $html, 'bytes' => strlen($html)];
        }

        throw new \InvalidArgumentException('A URL teve redirecionamentos demais.');
    }

    /** @return array{status: int, type: string, location: ?string, body: string} */
    private function request(string $url, string $host, int $port, string $ip): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('A leitura de páginas não está disponível.');
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('A página não pôde ser lida.');
        }
        $headers = [];
        $body = '';
        $tooLarge = false;
        $resolveHost = str_contains($host, ':') ? '['.$host.']' : $host;
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'SmartTasks/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $resolveHost, $port, $ip)],
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$headers): int {
                $headers[] = trim($header);

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    $tooLarge = true;

                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);
        curl_exec($handle);
        $error = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($tooLarge) {
            throw new \InvalidArgumentException('A página passa de 1 MB.');
        }
        if ($error !== 0 || $status === 0) {
            throw new \RuntimeException('A página não pôde ser lida.');
        }
        $location = null;
        $type = '';
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
            }
            if (stripos($header, 'Content-Type:') === 0) {
                $type = strtolower(trim(substr($header, 13)));
            }
        }
        if ($status >= 300 && $status < 400) {
            if ($location === null || $location === '') {
                throw new \RuntimeException('A página não pôde ser lida.');
            }

            return ['status' => $status, 'type' => $type, 'location' => $location, 'body' => ''];
        }

        return ['status' => $status, 'type' => $type, 'location' => null, 'body' => $body];
    }

    private function isHtml(string $type): bool
    {
        return str_contains($type, 'text/html') || str_contains($type, 'application/xhtml+xml');
    }

    private function resolveRedirect(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        $parts = parse_url($base);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Informe uma URL HTTP ou HTTPS.');
        }
        $origin = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }
        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        $path = $parts['path'] ?? '/';
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $origin.$directory.'/'.$location;
    }
}
