<?php

namespace App\Service\Scraping;

final class HtmlTextExtractor
{
    public function title(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return '';
        }
        $title = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

        return mb_substr($title, 0, 255);
    }

    public function extract(string $html): string
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $this->removeNoise($xpath);
        $root = $this->contentRoot($document, $xpath);

        return $this->normalize($this->renderChildren($root));
    }

    private function removeNoise(\DOMXPath $xpath): void
    {
        foreach ([
            '//script|//style|//noscript|//svg|//iframe|//template|//canvas',
            '//nav|//header|//footer|//aside|//form|//button|//select|//option|//input',
            '//*[@hidden or translate(@aria-hidden, "TRUE", "true") = "true"]',
            '//*[@role = "navigation" or @role = "banner" or @role = "contentinfo"]',
            '//*[@id = "breadcrumbs" or @id = "usernotes"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " page-tools ")
                or contains(concat(" ", normalize-space(@class), " "), " contribute ")
                or contains(concat(" ", normalize-space(@class), " "), " breadcrumbs ")
                or contains(concat(" ", normalize-space(@class), " "), " cookie-banner ")
                or contains(concat(" ", normalize-space(@class), " "), " site-menu ")
                or contains(concat(" ", normalize-space(@class), " "), " layout-menu ")]',
        ] as $query) {
            $nodes = $xpath->query($query);
            if ($nodes === false) {
                continue;
            }
            foreach (iterator_to_array($nodes) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function contentRoot(\DOMDocument $document, \DOMXPath $xpath): \DOMNode
    {
        foreach ([
            '//main|//*[@role = "main"]',
            '//article',
            '//*[@id = "layout-content"]|//*[@id = "content"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " main-content ")
                or contains(concat(" ", normalize-space(@class), " "), " article-content ")]',
        ] as $query) {
            $nodes = $xpath->query($query);
            if ($nodes === false || $nodes->length === 0) {
                continue;
            }
            $largest = null;
            $length = 0;
            foreach ($nodes as $node) {
                $candidateLength = mb_strlen(trim($node->textContent ?? ''));
                if ($candidateLength > $length) {
                    $largest = $node;
                    $length = $candidateLength;
                }
            }
            if ($largest instanceof \DOMNode && $length >= 200) {
                return $largest;
            }
        }

        return $document->getElementsByTagName('body')->item(0) ?? $document;
    }

    private function renderChildren(\DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $this->renderNode($child);
        }

        return $text;
    }

    private function renderNode(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return preg_replace('/\s+/u', ' ', $node->nodeValue ?? '') ?? '';
        }
        if (!$node instanceof \DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        if ($tag === 'br') {
            return "\n";
        }
        if ($tag === 'hr') {
            return "\n\n---\n\n";
        }
        if (preg_match('/^h([1-6])$/', $tag, $matches) === 1) {
            $title = $this->inlineText($node);

            return $title === '' ? '' : "\n\n".str_repeat('#', (int) $matches[1]).' '.$title."\n\n";
        }
        if ($tag === 'pre') {
            $code = $this->codeText($node->textContent ?? '');

            return $code === '' ? '' : "\n\n```\n".$code."\n```\n\n";
        }
        if ($tag === 'code') {
            $code = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');

            return $code === '' ? '' : '`'.str_replace('`', '\`', $code).'`';
        }
        if ($tag === 'ul' || $tag === 'ol') {
            return $this->renderList($node, $tag === 'ol');
        }
        if ($tag === 'table') {
            return $this->renderTable($node);
        }
        if ($tag === 'blockquote') {
            $content = trim($this->renderChildren($node));
            $lines = preg_split('/\R/u', $content) ?: [];

            return "\n\n".implode("\n", array_map(
                static fn (string $line): string => '> '.trim($line),
                $lines,
            ))."\n\n";
        }
        if ($tag === 'dt') {
            $term = $this->inlineText($node);

            return $term === '' ? '' : "\n\n".$term.":\n";
        }
        if ($tag === 'dd') {
            $definition = trim($this->renderChildren($node));

            return $definition === '' ? '' : '- '.$definition."\n";
        }

        $content = $this->renderChildren($node);
        if (in_array($tag, ['p', 'div', 'section', 'figure', 'figcaption', 'details', 'summary'], true)) {
            return "\n\n".$content."\n\n";
        }

        return $content;
    }

    private function renderList(\DOMElement $list, bool $ordered, int $depth = 0): string
    {
        $lines = [];
        $position = 1;
        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }
            $body = '';
            $nested = '';
            foreach ($child->childNodes as $part) {
                if ($part instanceof \DOMElement && in_array(strtolower($part->tagName), ['ul', 'ol'], true)) {
                    $nested .= $this->renderList($part, strtolower($part->tagName) === 'ol', $depth + 1);
                    continue;
                }
                $body .= $this->renderNode($part);
            }
            $body = trim(preg_replace('/\s*\R+\s*/u', ' ', $body) ?? '');
            if ($body !== '') {
                $marker = $ordered ? $position.'. ' : '- ';
                $lines[] = str_repeat('  ', $depth).$marker.$body;
            }
            if ($nested !== '') {
                $lines[] = trim($nested, "\n");
            }
            $position++;
        }

        return $lines === [] ? '' : "\n\n".implode("\n", $lines)."\n\n";
    }

    private function renderTable(\DOMElement $table): string
    {
        $xpath = new \DOMXPath($table->ownerDocument);
        $rows = $xpath->query('.//tr', $table);
        if ($rows === false) {
            return '';
        }
        $lines = [];
        foreach ($rows as $row) {
            if (!$row instanceof \DOMElement) {
                continue;
            }
            $cells = $xpath->query('./th|./td', $row);
            if ($cells === false || $cells->length === 0) {
                continue;
            }
            $values = [];
            foreach ($cells as $cell) {
                $values[] = str_replace('|', '\|', $this->inlineText($cell));
            }
            $lines[] = '| '.implode(' | ', $values).' |';
        }

        return $lines === [] ? '' : "\n\n".implode("\n", $lines)."\n\n";
    }

    private function inlineText(\DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');
    }

    private function codeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\R/u', $text) ?: [];
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim($lines[array_key_last($lines)]) === '') {
            array_pop($lines);
        }

        return rtrim(implode("\n", array_map(static fn (string $line): string => rtrim($line), $lines)));
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\R/u', $text) ?: [];
        $normalized = [];
        $inCode = false;
        $previous = null;
        $blank = false;
        foreach ($lines as $line) {
            if (trim($line) === '```') {
                $inCode = !$inCode;
                $normalized[] = '```';
                $previous = null;
                $blank = false;
                continue;
            }
            if ($inCode) {
                $normalized[] = rtrim($line);
                continue;
            }
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line) ?? '');
            if ($line === '') {
                if (!$blank && $normalized !== []) {
                    $normalized[] = '';
                    $blank = true;
                }
                continue;
            }
            if ($line === $previous) {
                continue;
            }
            $normalized[] = $line;
            $previous = $line;
            $blank = false;
        }

        return trim(implode("\n", $normalized));
    }
}
