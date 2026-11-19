<?php
// ============================================================
// GONCA LÖK — Blog HTML temizleyici (izin listesi tabanlı)
// Admin CKEditor/textarea içeriğini public sayfada güvenle basmak için.
// ============================================================
declare(strict_types=1);

function blog_sanitize_html(string $html): string
{
    $allowedTags = [
        'h2', 'h3', 'h4', 'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'figure', 'figcaption', 'code', 'pre',
    ];
    // Bu etiketler içerikleriyle birlikte tamamen atılır
    $dropWithContent = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'select', 'button', 'meta', 'link', 'base'];
    $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'code' => ['class'],
    ];

    if (trim($html) === '') {
        return '';
    }

    $previous = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    // UTF-8 aktarımını koru
    $wrapped = '<div id="__root__">' . $html . '</div>';
    $ok = $doc->loadHTML('<?xml encoding="UTF-8"?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_use_internal_errors($previous);
    if (!$ok) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    $root = $doc->getElementById('__root__');
    if (!$root) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    $sanitizeNode = static function (DOMNode $node) use (&$sanitizeNode, $allowedTags, $dropWithContent, $allowedAttributes, $doc): void {
        /** @var DOMElement[] $children */
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue; // metin düğümleri ve entity'ler kalır (zararsız)
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, $dropWithContent, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!in_array($tag, $allowedTags, true)) {
                // İzin verilmeyen etiket: içeriğini koruyup etiketi düşür
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $allowedAttrs = $allowedAttributes[$tag] ?? [];
            $attrs = [];
            foreach ($child->attributes as $attr) {
                $attrs[] = $attr;
            }
            foreach ($attrs as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowedAttrs, true) || str_starts_with($name, 'on')) {
                    $child->removeAttribute($attr->name);
                    continue;
                }
                $value = trim($attr->value);
                if ($name === 'href' || $name === 'src') {
                    $lower = strtolower($value);
                    $hasScheme = (bool) preg_match('/^[a-z][a-z0-9+.-]*:/', $lower);
                    $isHttp = str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://');
                    $isProtocolRelative = str_starts_with($lower, '//');
                    $isRelative = !$hasScheme && !$isProtocolRelative && $value !== '' && !str_contains($value, "\n") && !str_contains($value, "\r");
                    $isAnchor = str_starts_with($value, '#');
                    $isMail = $tag === 'a' && str_starts_with($lower, 'mailto:');
                    if (!($isHttp || $isRelative || $isAnchor || $isMail)) {
                        $child->removeAttribute($attr->name);
                        continue;
                    }
                }
                if ($name === 'target' && $value !== '_blank') {
                    $child->removeAttribute('target');
                }
            }
            if ($tag === 'a' && strtolower((string) $child->getAttribute('target')) === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
            $sanitizeNode($child);
        }
    };

    $sanitizeNode($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}
