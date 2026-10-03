<?php

namespace App\Service\Scraping;

final class PublicUrlGuard
{
    public function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048) {
            throw new \InvalidArgumentException('Informe uma URL HTTP ou HTTPS.');
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new \InvalidArgumentException('Informe uma URL HTTP ou HTTPS.');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Informe uma URL HTTP ou HTTPS.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('A URL não pode conter usuário ou senha.');
        }
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if ($host === '') {
            throw new \InvalidArgumentException('Informe uma URL HTTP ou HTTPS.');
        }
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }
        $this->assertHostAllowed($host);
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('A porta da URL é inválida.');
        }
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        $portSuffix = $defaultPort ? '' : ':'.$port;
        $formattedHost = str_contains($host, ':') ? '['.$host.']' : $host;

        return $scheme.'://'.$formattedHost.$portSuffix.$path.$query;
    }

    public function assertHostAllowed(string $host): void
    {
        $blocked = ['localhost', 'localhost.localdomain', 'metadata.google.internal'];
        if (in_array($host, $blocked, true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        ) {
            throw new \InvalidArgumentException('Endereços internos não são permitidos.');
        }
        if (str_contains($host, ':') || preg_match('/^[0-9.]+$/', $host) === 1) {
            if (filter_var($host, FILTER_VALIDATE_IP) === false) {
                throw new \InvalidArgumentException('O endereço da URL é inválido.');
            }
            $this->assertPublicIp($host);

            return;
        }
        if (preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            throw new \InvalidArgumentException('O domínio da URL é inválido.');
        }
        foreach ($this->resolve($host) as $ip) {
            $this->assertPublicIp($ip);
        }
    }

    /** @return list<string> */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }
        $ips = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
        if ($ips === []) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }
        $ips = array_values(array_unique($ips));
        if ($ips === []) {
            throw new \InvalidArgumentException('Não foi possível resolver o domínio.');
        }

        return $ips;
    }

    public function assertPublicIp(string $ip): void
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            throw new \InvalidArgumentException('O endereço resolvido é inválido.');
        }
        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $mapped = inet_ntop(substr($packed, 12));
            if (is_string($mapped)) {
                $ip = $mapped;
            }
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new \InvalidArgumentException('Endereços internos não são permitidos.');
        }
    }
}
