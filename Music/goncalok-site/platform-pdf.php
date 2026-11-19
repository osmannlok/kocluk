<?php
// ============================================================
// GONCA LÖK — Basit PDF yazıcı (harici kütüphane gerektirmez)
// ============================================================

final class PlatformPdf
{
    private array $pages = [];
    private string $current = '';
    private float $width = 595.28;
    private float $height = 841.89;
    private float $fontSize = 10.0;
    private string $fontKey = 'F1';

    public function __construct()
    {
        $this->newPage();
    }

    public function newPage(): void
    {
        if ($this->current !== '') { $this->pages[] = $this->current; }
        $this->current = '';
    }

    public function setFont(float $size, bool $bold = false): void
    {
        $this->fontSize = max(5, min(48, $size));
        $this->fontKey = $bold ? 'F2' : 'F1';
    }

    public function pageWidth(): float { return $this->width; }
    public function pageHeight(): float { return $this->height; }

    public function ensureSpace(float &$y, float $needed): void
    {
        if ($y + $needed > $this->height - 48) {
            $this->newPage();
            $y = 56.0;
        }
    }

    public function text(float $x, float $y, string $text): void
    {
        $bytes = $this->encode($text);
        $pdfY = $this->height - $y;
        $this->current .= sprintf(
            "BT /%s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $this->fontKey, $this->fontSize, $x, $pdfY, $bytes
        );
    }

    public function textRight(float $rightX, float $y, string $text): void
    {
        $this->text($rightX - $this->measure($text), $y, $text);
    }

    public function measure(string $text): float
    {
        $length = mb_strlen($text, 'UTF-8');
        return max(0, $length) * $this->fontSize * 0.52;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.7): void
    {
        $this->current .= sprintf(
            "%.2f w %.2f %.2f m %.2f %.2f l S\n",
            $width, $x1, $this->height - $y1, $x2, $this->height - $y2
        );
    }

    public function rect(float $x, float $y, float $w, float $h, bool $fill = false): void
    {
        $op = $fill ? 'f' : 'S';
        $this->current .= sprintf(
            "%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re %s\n",
            $fill ? 0.96 : 0, $fill ? 0.94 : 0, $fill ? 0.87 : 0,
            $x, $this->height - $y - $h, $w, $h, $op
        );
    }

    public function heading(string $text, float &$y): void
    {
        $this->ensureSpace($y, 28);
        $this->setFont(13, true);
        $this->rect(48, $y - 4, 499, 22, true);
        $this->text(56, $y + 11, $text);
        $y += 32;
        $this->setFont(10);
    }

    public function row(string $label, string $value, float &$y, float $labelWidth = 135): void
    {
        $this->ensureSpace($y, 16);
        $this->setFont(9, true);
        $this->text(56, $y, $label);
        $this->setFont(9);
        $this->line(56 + $labelWidth, $y - 3, 56 + $labelWidth, $y + 11, 0.3);
        $y = $this->paragraph(56 + $labelWidth + 10, $y, $value, 499 - $labelWidth - 10, 12, 9, false);
        $y += 4;
    }

    public function paragraph(
        float $x,
        float $y,
        string $text,
        float $maxWidth,
        float $lineHeight = 13,
        ?float $size = null,
        bool $bold = false
    ): float {
        $size = $size ?? $this->fontSize;
        $this->setFont($size, $bold);
        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $rawLine) {
            $rawLine = trim($rawLine);
            if ($rawLine === '') { $lines[] = ''; continue; }
            $current = '';
            foreach (preg_split('/\s+/u', $rawLine) ?: [] as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if ($this->measure($candidate) > $maxWidth && $current !== '') {
                    $lines[] = $current;
                    $current = $word;
                } else {
                    $current = $candidate;
                }
            }
            if ($current !== '') { $lines[] = $current; }
        }
        foreach ($lines as $line) {
            $this->ensureSpace($y, $lineHeight);
            $this->text($x, $y, $line);
            $y += $lineHeight;
        }
        return $y;
    }

    public function bullet(string $text, float &$y, float $x = 60, float $maxWidth = 480): void
    {
        $this->ensureSpace($y, 16);
        $this->setFont(10);
        $this->text($x, $y, '•');
        $y = $this->paragraph($x + 14, $y, $text, $maxWidth, 13);
        $y += 4;
    }

    public function output(): string
    {
        $pages = $this->pages;
        if ($this->current !== '') { $pages[] = $this->current; }
        $pageCount = max(1, count($pages));

        $encoding = "/Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [ "
            . "208 /Gbreve 221 /Idotaccent 222 /Scedilla 240 /gbreve 253 /dotlessi 254 /scedilla ] >>";

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Count " . $pageCount . " /Kids [" . $this->kids($pageCount) . "] >>",
            3 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica " . $encoding . " >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold " . $encoding . " >>",
        ];

        $content = '';
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObj = 5 + ($i * 2);
            $contentObj = $pageObj + 1;
            $stream = $pages[$i] ?? '';
            $objects[$pageObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] "
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents " . $contentObj . " 0 R >>";
            $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        ksort($objects);
        $maxObj = max(array_keys($objects));
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObj; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF\n";
        return $pdf;
    }

    private function kids(int $pageCount): string
    {
        $parts = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $parts[] = (5 + ($i * 2)) . ' 0 R';
        }
        return implode(' ', $parts);
    }

    private function encode(string $text): string
    {
        $converted = @iconv('UTF-8', 'CP1254//IGNORE', $text);
        if ($converted === false) { $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: ''; }
        $converted = str_replace(["\r", "\n", "\t"], ' ', $converted);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
    }
}

function platform_pdf_download(string $filename, string $pdf): void
{
    if (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
