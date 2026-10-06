<?php

namespace App\Service\Scraping;

final class SameSiteLinkCollector
{
    public function __construct(private readonly PublicUrlGuard $guard)
    {
    }

    /** @return list<string> */
    public function collect(string $pageUrl, string $html): array
    {
        $seed = $this->guard->normalize($pageUrl);
        $seedHost = $this->siteHost($seed);
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $found = [];
        $seen = [$this->key($seed) => true];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto:|javascript:|data:)/i', $href) === 1) {
                continue;
            }
            try {
                $absolute = $this->absolute($seed, $href);
                $normalized = $this->guard->normalize($absolute);
            } catch (\Throwable) {
                continue;
            }
            if ($this->siteHost($normalized) !== $seedHost) {
                continue;
            }
            $key = $this->key($normalized);
            if (isset($seen[$key]) || $this->skippedExtension($normalized)) {
                continue;
            }
            $seen[$key] = true;
            $found[] = $normalized;
        }

        usort($found, function (string $left, string $right) use ($seed): int {
            return $this->rank($right, $seed) <=> $this->rank($left, $seed);
        });

        return $found;
    }

    private function rank(string $url, string $seed): int
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $seedPath = strtolower((string) parse_url($seed, PHP_URL_PATH));
        $seedDir = str_ends_with($seedPath, '/') ? $seedPath : (preg_replace('#/[^/]*$#', '/', $seedPath) ?: '/');
        $score = 0;
        if ($seedDir !== '/' && str_starts_with($path, $seedDir)) {
            $score += 100;
        }
        if (str_contains($path, '/manual/')) {
            $score += 40;
        }
        if (preg_match('#^/(downloads|support|get-involved|menu|lookup-form|releases)(\.php)?(/|$)#', $path) === 1 || $path === '/' || $path === '/index.php') {
            $score -= 50;
        }

        return $score;
    }

    private function absolute(string $base, string $href): string
    {
        if (preg_match('/^https?:\/\//i', $href) === 1) {
            return preg_replace('/#.*$/', '', $href) ?? $href;
        }
        $parts = parse_url($base);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('A URL de origem é inválida.');
        }
        $prefix = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $prefix .= ':'.$parts['port'];
        }
        if (str_starts_with($href, '/')) {
            return $prefix.(preg_replace('/#.*$/', '', $href) ?? $href);
        }
        $path = $parts['path'] ?? '/';
        $directory = str_ends_with($path, '/') ? $path : preg_replace('#/[^/]*$#', '/', $path);
        $joined = $prefix.$directory.$href;

        return preg_replace('/#.*$/', '', $joined) ?? $joined;
    }

    private function siteHost(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private function key(string $url): string
    {
        return rtrim($url, '/');
    }

    private function skippedExtension(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_contains($path, '/_app/')
            || preg_match('/\.(pdf|zip|gz|png|jpe?g|gif|svg|css|js|xml|mp[34]|woff2?)$/', $path) === 1;
    }
}
