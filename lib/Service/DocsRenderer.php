<?php

namespace CustomAI\SeoFilter\Service;

/**
 * Minimal Markdown → HTML for README in module settings (no external deps).
 */
final class DocsRenderer
{
    public static function readmeHtml(): string
    {
        $path = dirname(__DIR__, 2) . '/README.md';
        if (!is_file($path)) {
            return '<p>Файл README.md не найден.</p>';
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return '<p>README.md пуст.</p>';
        }

        // Strip UTF-8 BOM if present
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        return self::toHtml($raw);
    }

    public static function toHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $html = [];
        $i = 0;
        $n = count($lines);
        $inUl = false;
        $inOl = false;
        $para = [];

        $closeLists = static function () use (&$html, &$inUl, &$inOl): void {
            if ($inUl) {
                $html[] = '</ul>';
                $inUl = false;
            }
            if ($inOl) {
                $html[] = '</ol>';
                $inOl = false;
            }
        };

        $flushPara = static function () use (&$html, &$para): void {
            if ($para === []) {
                return;
            }
            $html[] = '<p>' . self::inline(implode(' ', $para)) . '</p>';
            $para = [];
        };

        while ($i < $n) {
            $line = $lines[$i];
            $trim = trim($line);

            if ($trim === '') {
                $flushPara();
                $closeLists();
                $i++;
                continue;
            }

            if (str_starts_with($trim, '```')) {
                $flushPara();
                $closeLists();
                $lang = trim(substr($trim, 3));
                $code = [];
                $i++;
                while ($i < $n && !str_starts_with(trim($lines[$i]), '```')) {
                    $code[] = $lines[$i];
                    $i++;
                }
                if ($i < $n) {
                    $i++;
                }
                $cls = $lang !== ''
                    ? ' class="language-' . htmlspecialchars($lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                    : '';
                $html[] = '<pre><code' . $cls . '>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</code></pre>';
                continue;
            }

            if (preg_match('/^\|(.+)\|$/u', $trim)) {
                $flushPara();
                $closeLists();
                $tableRows = [];
                while ($i < $n && preg_match('/^\|(.+)\|$/u', trim($lines[$i]))) {
                    $row = trim($lines[$i]);
                    if (!preg_match('/^\|[\s\-:|]+\|$/u', $row)) {
                        $cells = array_map('trim', explode('|', trim($row, '|')));
                        $tableRows[] = $cells;
                    }
                    $i++;
                }
                if ($tableRows !== []) {
                    $html[] = '<table class="atm-seo-docs-table">';
                    foreach ($tableRows as $ri => $cells) {
                        $tag = $ri === 0 ? 'th' : 'td';
                        $html[] = '<tr>';
                        foreach ($cells as $cell) {
                            $html[] = '<' . $tag . '>' . self::inline($cell) . '</' . $tag . '>';
                        }
                        $html[] = '</tr>';
                        if ($ri === 0) {
                            // continue body rows
                        }
                    }
                    $html[] = '</table>';
                }
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/u', $trim, $m)) {
                $flushPara();
                $closeLists();
                $level = strlen($m[1]);
                $html[] = '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . '>';
                $i++;
                continue;
            }

            if ($trim === '---' || $trim === '***' || $trim === '___') {
                $flushPara();
                $closeLists();
                $html[] = '<hr>';
                $i++;
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/u', $trim, $m)) {
                $flushPara();
                if ($inOl) {
                    $html[] = '</ol>';
                    $inOl = false;
                }
                if (!$inUl) {
                    $html[] = '<ul>';
                    $inUl = true;
                }
                $html[] = '<li>' . self::inline($m[1]) . '</li>';
                $i++;
                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/u', $trim, $m)) {
                $flushPara();
                if ($inUl) {
                    $html[] = '</ul>';
                    $inUl = false;
                }
                if (!$inOl) {
                    $html[] = '<ol>';
                    $inOl = true;
                }
                $html[] = '<li>' . self::inline($m[1]) . '</li>';
                $i++;
                continue;
            }

            $closeLists();
            $para[] = $trim;
            $i++;
        }

        $flushPara();
        $closeLists();

        return implode("\n", $html);
    }

    private static function inline(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // `code`
        $text = preg_replace_callback('/`([^`]+)`/', static function (array $m): string {
            return '<code>' . $m[1] . '</code>';
        }, $text) ?? $text;

        // **bold**
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text) ?? $text;

        // [label](url) — only relative or http(s)
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            static function (array $m): string {
                $href = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (!preg_match('#^(https?://|/|mailto:)#i', $href)) {
                    return $m[0];
                }
                return '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
            },
            $text
        ) ?? $text;

        return $text;
    }
}
