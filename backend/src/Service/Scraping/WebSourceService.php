<?php

namespace App\Service\Scraping;

use App\Entity\Document;
use App\Entity\SourceImport;
use App\Entity\User;
use App\Exception\DocumentNotFoundException;
use App\Repository\DocumentChunkRepository;
use App\Repository\DocumentRepository;
use App\Repository\SourceImportRepository;
use App\Service\Document\DocumentIndexer;

class WebSourceService
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly SourceImportRepository $imports,
        private readonly DocumentChunkRepository $chunks,
        private readonly DocumentIndexer $indexer,
        private readonly HtmlPageFetcher $fetcher,
        private readonly HtmlTextExtractor $html,
        private readonly PublicUrlGuard $guard,
        private readonly SameSiteLinkCollector $links,
        private readonly RobotsTxt $robots,
    ) {
    }

    public function import(User $owner, string $url): Document
    {
        return $this->importMany($owner, $url, false)[0];
    }

    /** @return list<Document> */
    public function importMany(User $owner, string $url, bool $followLinks): array
    {
        $normalized = $this->guard->normalize($url);
        $policy = $this->robots->load($normalized);
        if ($policy['readable'] && !$policy['allows']($normalized)) {
            throw new \InvalidArgumentException('O robots.txt não permite esta URL.');
        }
        if ($followLinks) {
            set_time_limit(0);
        }

        $queue = [$normalized];
        $seen = [$this->urlKey($normalized) => true];
        $collected = [];
        $title = '';
        $root = $normalized;

        $maxPages = $followLinks ? 200 : 1;
        while ($queue !== [] && count($collected) < $maxPages) {
            $current = array_shift($queue);
            if ($policy['readable'] && !$policy['allows']($current)) {
                continue;
            }
            try {
                $page = $this->fetcher->fetch($current);
            } catch (\Throwable) {
                continue;
            }
            $seen[$this->urlKey($page['url'])] = true;
            if ($title === '') {
                $root = $page['url'];
            }
            $existing = $this->documents->findSourceByUrl($owner, $page['url']);
            if ($existing instanceof Document) {
                if ($title === '') {
                    $title = $existing->getOriginalName();
                }
                $collected[$this->urlKey($page['url'])] = $existing;
            } else {
                $stored = $this->store($owner, $page, null);
                $collected[$this->urlKey($page['url'])] = $stored;
                if ($title === '') {
                    $title = $stored->getOriginalName();
                }
            }
            if (!$followLinks || !$policy['readable'] || count($collected) >= $maxPages) {
                break;
            }
            foreach ($this->links->collect($page['url'], $page['html']) as $link) {
                if (count($collected) + count($queue) >= $maxPages) {
                    break;
                }
                $linkKey = $this->urlKey($link);
                if (isset($seen[$linkKey])) {
                    continue;
                }
                $seen[$linkKey] = true;
                $queue[] = $link;
            }
            if ($queue !== []) {
                usleep(200000);
            }
        }

        $documents = array_values($collected);
        $this->group($owner, $documents, $root, $title);
        $this->adoptLoosePages($owner);

        return $documents;
    }

    /** @param list<Document> $pages */
    private function group(User $owner, array $pages, string $rootUrl, string $title): void
    {
        $fresh = array_values(array_filter(
            $pages,
            static fn (Document $page): bool => $page->getImport() === null,
        ));
        $import = $this->findImportByRoot($owner, $rootUrl);
        if (!$import instanceof SourceImport && count($fresh) < 2) {
            return;
        }
        if ($fresh === []) {
            return;
        }
        if (!$import instanceof SourceImport) {
            $import = new SourceImport();
            $import->setUser($owner);
            $import->setRootUrl($rootUrl);
            $import->setTitle($title !== '' ? $title : $rootUrl);
            $this->imports->save($import);
        }
        foreach ($fresh as $page) {
            $page->setImport($import);
            $this->documents->save($page);
        }
    }

    private function findImportByRoot(User $owner, string $rootUrl): ?SourceImport
    {
        $key = $this->urlKey($rootUrl);
        foreach ($this->imports->findByOwner($owner) as $import) {
            if ($this->urlKey($import->getRootUrl()) === $key) {
                return $import;
            }
        }

        return null;
    }

    private function adoptLoosePages(User $owner): void
    {
        $byHost = [];
        foreach ($this->imports->findByOwner($owner) as $import) {
            $host = $this->host($import->getRootUrl());
            if ($host === '') {
                continue;
            }
            $current = $byHost[$host] ?? null;
            if (!$current instanceof SourceImport || $this->preferImport($import, $current)) {
                $byHost[$host] = $import;
            }
        }
        if ($byHost === []) {
            return;
        }

        $manager = $this->documents->getEntityManager();
        $dirty = false;
        foreach ($this->documents->findUngroupedSources($owner) as $page) {
            $url = $page->getSourceUrl();
            if ($url === null) {
                continue;
            }
            $import = $byHost[$this->host($url)] ?? null;
            if (!$import instanceof SourceImport) {
                continue;
            }
            $page->setImport($import);
            $manager->persist($page);
            $dirty = true;
        }
        if ($dirty) {
            $manager->flush();
        }
    }

    private function preferImport(SourceImport $candidate, SourceImport $current): bool
    {
        $candidatePath = strlen((string) parse_url($candidate->getRootUrl(), PHP_URL_PATH));
        $currentPath = strlen((string) parse_url($current->getRootUrl(), PHP_URL_PATH));
        if ($candidatePath !== $currentPath) {
            return $candidatePath < $currentPath;
        }

        return ($candidate->getId() ?? PHP_INT_MAX) < ($current->getId() ?? PHP_INT_MAX);
    }

    private function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    private function urlKey(string $url): string
    {
        return rtrim($url, '/');
    }

    public function retry(User $owner, int $id): Document
    {
        $document = $this->requireSource($owner, $id);
        if ($document->getStatus() !== Document::STATUS_FAILED) {
            throw new \InvalidArgumentException('Somente fontes com falha podem ser reprocessadas.');
        }
        $source = $document->getSourceUrl();
        if ($source === null || $source === '') {
            throw new \InvalidArgumentException('A fonte não possui URL.');
        }
        $page = $this->fetcher->fetch($source);
        $this->chunks->deleteByDocument($document);

        return $this->store($owner, $page, $document);
    }

    /** @return array{groups: list<array<string, mixed>>, pages: list<array<string, mixed>>} */
    public function catalog(User $owner): array
    {
        $this->adoptLoosePages($owner);
        $groups = [];
        foreach ($this->imports->findByOwner($owner) as $import) {
            $pages = $this->documents->findByImport($owner, $import);
            if ($pages === []) {
                continue;
            }
            $groups[] = [
                'id' => $import->getId(),
                'title' => $import->getTitle(),
                'root_url' => $import->getRootUrl(),
                'page_count' => count($pages),
                'chunk_count' => array_sum(array_map(static fn (Document $page): int => $page->getChunkCount(), $pages)),
                'created_at' => $import->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'pages' => array_map(static fn (Document $page): array => $page->toArray(), $pages),
            ];
        }

        return [
            'groups' => $groups,
            'pages' => array_map(
                static fn (Document $page): array => $page->toArray(),
                $this->documents->findUngroupedSources($owner),
            ),
        ];
    }

    public function delete(User $owner, int $id): void
    {
        $document = $this->requireSource($owner, $id);
        $import = $document->getImport();
        $this->documents->remove($document);
        if ($import instanceof SourceImport && $import->getId() !== null && $this->documents->countByImport($owner, $import) === 0) {
            $this->imports->remove($import);
        }
    }

    public function deleteGroup(User $owner, int $id): void
    {
        $import = $this->imports->findOneForOwner($id, $owner);
        if (!$import instanceof SourceImport) {
            throw new DocumentNotFoundException('Importação não encontrada.');
        }
        $this->imports->remove($import);
    }

    /** @param array{url: string, html: string, bytes: int} $page */
    private function store(User $owner, array $page, ?Document $existing): Document
    {
        $text = $this->html->extract($page['html']);
        $title = $this->html->title($page['html']);
        $document = $existing ?? new Document();
        if ($existing === null) {
            $document->setUser($owner);
            $document->setStoredName('url-'.bin2hex(random_bytes(16)));
        }
        $document->setOriginalName($title !== '' ? $title : mb_substr($page['url'], 0, 255));
        $document->setMimeType('text/html');
        $document->setExtension('html');
        $document->setSizeBytes($page['bytes']);
        $document->setSourceUrl(mb_substr($page['url'], 0, 2048));
        $document->setStatus(Document::STATUS_PROCESSING);
        $document->setErrorMessage(null);
        $document->setChunkCount(0);
        $this->documents->save($document);
        if ($text === '') {
            $document->setStatus(Document::STATUS_FAILED);
            $document->setErrorMessage('A página não possui texto útil.');
            $document->touch();
            $this->documents->save($document);

            return $document;
        }

        return $this->indexer->index($document, $text, [
            'source_url' => $document->getSourceUrl() ?? '',
        ]);
    }

    private function requireSource(User $owner, int $id): Document
    {
        $document = $this->documents->findOneForOwner($id, $owner);
        if (!$document instanceof Document || $document->getSourceUrl() === null) {
            throw new DocumentNotFoundException('Fonte não encontrada.');
        }

        return $document;
    }
}
