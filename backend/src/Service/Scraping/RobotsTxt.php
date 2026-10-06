<?php

namespace App\Service\Scraping;

final class RobotsTxt
{
    private const AGENT = 'smarttasks';

    public function __construct(private readonly PublicUrlGuard $guard)
    {
    }

    /**
     * @return array{readable: bool, delay: int, allows: callable(string): bool}
     */
    public function load(string $pageUrl): array
    {
        $parts = parse_url($pageUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $this->closed();
        }
        $robotsUrl = $parts['scheme'].'://'.$parts['host'].'/robots.txt';
        try {
            $body = $this->fetch($this->guard->normalize($robotsUrl));
        } catch (\Throwable) {
            return $this->closed();
        }
        if ($body === null) {
            return ['readable' => true, 'delay' => 1, 'allows' => static fn (string $url): bool => true];
        }

        $group = $this->group($body);
        $delay = max(1, min(5, $group['delay'] > 0 ? $group['delay'] : 1));

        return [
            'readable' => true,
            'delay' => $delay,
            'allows' => fn (string $url): bool => $this->allows($url, $group['rules']),
        ];
    }

    /** @param list<array{0: string, 1: string}> $rules */
    private function allows(string $url, array $rules): bool
    {
        $parts = parse_url($url);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '/') : '/';
        if ($path === '') {
            $path = '/';
        }
        if (is_array($parts) && isset($parts['query'])) {
            $path .= '?'.$parts['query'];
        }
        $best = -1;
        $allowed = true;
        foreach ($rules as [$type, $rule]) {
            if ($rule === '' || !$this->matches($path, $rule)) {
                continue;
            }
            $length = strlen($rule);
            if ($length < $best) {
                continue;
            }
            if ($length === $best && $type !== 'allow') {
                continue;
            }
            $best = $length;
            $allowed = $type === 'allow';
        }

        return $allowed;
    }

    private function matches(string $path, string $rule): bool
    {
        $quoted = preg_quote($rule, '/');
        $pattern = '/^'.str_replace('\\*', '.*', $quoted).'/';

        return preg_match($pattern, $path) === 1;
    }

    /** @return array{delay: int, rules: list<array{0: string, 1: string}>} */
    private function group(string $body): array
    {
        $groups = [];
        $index = -1;
        foreach (preg_split('/\r\n|\n|\r/', $body) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s*#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            if ($key === 'user-agent') {
                if ($index < 0 || $groups[$index]['rules'] !== []) {
                    $groups[] = ['agents' => [], 'rules' => [], 'delay' => 0];
                    $index = array_key_last($groups);
                }
                $groups[$index]['agents'][] = strtolower($value);
                continue;
            }
            if ($index < 0) {
                continue;
            }
            if ($key === 'allow' || $key === 'disallow') {
                $groups[$index]['rules'][] = [$key, $value];
            } elseif ($key === 'crawl-delay' && is_numeric($value)) {
                $groups[$index]['delay'] = (int) $value;
            }
        }

        $fallback = ['delay' => 0, 'rules' => []];
        $specific = null;
        foreach ($groups as $group) {
            if (in_array('*', $group['agents'], true)) {
                $fallback = $group;
            }
            if (in_array(self::AGENT, $group['agents'], true) || in_array(self::AGENT.'/1.0', $group['agents'], true)) {
                $specific = $group;
            }
        }

        return $specific ?? $fallback;
    }

    private function fetch(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'], $parts['scheme'])) {
            throw new \InvalidArgumentException('O robots.txt não pôde ser lido.');
        }
        $host = strtolower((string) $parts['host']);
        $port = (int) ($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80));
        $ips = $this->guard->resolve($host);
        foreach ($ips as $ip) {
            $this->guard->assertPublicIp($ip);
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('O robots.txt não pôde ser lido.');
        }
        $body = '';
        $status = 0;
        $resolveHost = str_contains($host, ':') ? '['.$host.']' : $host;
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'SmartTasks/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $resolveHost, $port, $ips[0])],
            CURLOPT_HTTPHEADER => ['Accept: text/plain'],
        ]);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if (!is_string($response)) {
            throw new \RuntimeException('O robots.txt não pôde ser lido.');
        }
        if ($status === 404) {
            return null;
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('O robots.txt não pôde ser lido.');
        }

        return substr($response, 0, 65536);
    }

    /** @return array{readable: bool, delay: int, allows: callable(string): bool} */
    private function closed(): array
    {
        return [
            'readable' => false,
            'delay' => 1,
            'allows' => static fn (string $url): bool => false,
        ];
    }
}
