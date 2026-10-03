<?php

namespace App\Service\Document;

final class TextChunker
{
    /**
     * @return list<array{content: string, start: int, end: int}>
     */
    public function split(string $text, int $size = 800, int $overlap = 150): array
    {
        $text = trim($text);
        $length = mb_strlen($text);
        if ($text === '' || $size < 1 || $overlap < 0 || $overlap >= $size) {
            return [];
        }
        if ($length <= $size) {
            return [['content' => $text, 'start' => 0, 'end' => $length]];
        }

        $chunks = [];
        $start = 0;
        while ($start < $length) {
            $end = min($start + $size, $length);
            if ($end < $length) {
                $window = mb_substr($text, $start, $end - $start);
                $breakAt = $this->bestBreak($window, (int) ($size / 2));
                if ($breakAt !== null) {
                    $end = $start + $breakAt;
                }
                if ($this->insideCodeBlock(mb_substr($text, 0, $end))) {
                    $closingFence = mb_strpos($text, '```', $end);
                    if ($closingFence !== false) {
                        $end = min($length, $closingFence + 3);
                    }
                }
            }

            $content = trim(mb_substr($text, $start, $end - $start));
            if ($content !== '') {
                $chunks[] = ['content' => $content, 'start' => $start, 'end' => $end];
            }
            if ($end >= $length) {
                break;
            }

            $next = max($end - $overlap, $start + 1);
            $overlapText = mb_substr($text, $next, $end - $next);
            foreach (["\n\n", "\n", '. ', '? ', '! ', ' '] as $separator) {
                $boundary = mb_strpos($overlapText, $separator);
                if ($boundary !== false) {
                    $next += $boundary + mb_strlen($separator);
                    break;
                }
            }
            if ($this->insideCodeBlock(mb_substr($text, 0, $next))) {
                $prefix = mb_substr($text, 0, $next);
                $openingFence = mb_strrpos($prefix, '```');
                $next = $openingFence !== false && $openingFence > $start ? $openingFence : $end;
            }
            if ($next <= $start) {
                $next = $end;
            }
            $start = $next;
        }

        return $chunks;
    }

    private function bestBreak(string $window, int $minimum): ?int
    {
        foreach (["\n\n", "\n", '. ', '? ', '! ', '; ', ': ', ' '] as $separator) {
            $position = mb_strrpos($window, $separator);
            if ($position !== false && $position >= $minimum) {
                return $position + mb_strlen($separator);
            }
        }

        return null;
    }

    private function insideCodeBlock(string $text): bool
    {
        return substr_count($text, '```') % 2 === 1;
    }
}
