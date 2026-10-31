<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

/**
 * Minimal PDF writer: A4 pages, Helvetica (regular/bold), text, lines and boxes.
 * Coordinates are in points from the top-left corner. Text is encoded as Windows-1252,
 * which covers German and French characters.
 */
final class SimplePdf
{
    public const W = 595.28;
    public const H = 841.89;

    /** Helvetica glyph widths for ASCII 32–126 (per 1000 units). */
    private const WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var string[] */
    private array $pages = [];
    private int $page = -1;
    private string $title = '';

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->page = count($this->pages) - 1;
    }

    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $rgb = [0, 0, 0]): void
    {
        $this->pages[$this->page] .= sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1', $size, $rgb[0], $rgb[1], $rgb[2], $x, self::H - $y, self::escape($s)
        );
    }

    public function rect(float $x, float $y, float $w, float $h, float $lineWidth = 0.6): void
    {
        $this->pages[$this->page] .= sprintf("0 0 0 RG %.2F w %.2F %.2F %.2F %.2F re S\n", $lineWidth, $x, self::H - $y - $h, $w, $h);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $lineWidth = 0.5, float $gray = 0): void
    {
        $this->pages[$this->page] .= sprintf("%.3F G %.2F w %.2F %.2F m %.2F %.2F l S\n", $gray, $lineWidth, $x1, self::H - $y1, $x2, self::H - $y2);
    }

    public static function width(string $s, float $size, bool $bold = false): float
    {
        $w = 0;
        $enc = self::encode($s);
        $len = strlen($enc);
        for ($i = 0; $i < $len; $i++) {
            $o = ord($enc[$i]);
            $w += ($o >= 32 && $o <= 126) ? self::WIDTHS[$o - 32] : 556;
        }
        return $w * $size / 1000 * ($bold ? 1.06 : 1.0);
    }

    /** Word-wrap text into lines that fit $maxWidth. */
    public function wrap(string $s, float $maxWidth, float $size, bool $bold = false): array
    {
        $words = preg_split('/\s+/u', trim($s)) ?: [''];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $try = $cur === '' ? $w : $cur . ' ' . $w;
            if ($cur !== '' && self::width($try, $size, $bold) > $maxWidth) {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $try;
            }
        }
        $lines[] = $cur;
        return $lines;
    }

    public function output(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        $n = 5;
        foreach ($this->pages as $content) {
            $filter = '';
            if (function_exists('gzcompress')) {
                $content = (string) gzcompress($content);
                $filter = ' /Filter /FlateDecode';
            }
            $objects[$n] = '<< /Length ' . strlen($content) . $filter . " >>\nstream\n" . $content . "\nendstream";
            $objects[$n + 1] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::W, self::H, $n
            );
            $kids[] = ($n + 1) . ' 0 R';
            $n += 2;
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $info = $n;
        $objects[$info] = '<< /Title (' . self::escape($this->title) . ') /Producer (Culina ' . CULINA_VERSION . ') >>';
        ksort($objects);

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . ($info + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $info; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= 'trailer << /Size ' . ($info + 1) . ' /Root 1 0 R /Info ' . $info . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $out;
    }

    private static function encode(string $s): string
    {
        $c = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        return $c === false ? (string) preg_replace('/[^\x20-\x7E]/', '?', $s) : $c;
    }

    private static function escape(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], self::encode($s));
    }
}
