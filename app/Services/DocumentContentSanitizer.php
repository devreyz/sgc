<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class DocumentContentSanitizer
{
    private const TAGS = [
        'p', 'div', 'span', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's',
        'blockquote', 'ul', 'ol', 'li', 'a', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'img',
    ];

    private const ATTRIBUTES = [
        'class', 'style', 'href', 'target', 'rel', 'src', 'alt', 'title', 'width', 'height', 'colspan', 'rowspan',
    ];

    private const STYLES = [
        'text-align', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'color', 'background-color',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding', 'padding-top',
        'padding-right', 'padding-bottom', 'padding-left', 'border', 'border-top', 'border-right', 'border-bottom',
        'border-left', 'border-collapse', 'width', 'height', 'max-width', 'line-height', 'page-break-before',
        'page-break-after', 'page-break-inside', 'vertical-align',
    ];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '<p><br></p>';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="sgc-document-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('sgc-document-root');
        if (! $root) {
            return '<p><br></p>';
        }

        $this->cleanChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result) ?: '<p><br></p>';
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (! in_array($tag, self::TAGS, true)) {
                $this->unwrap($node);

                continue;
            }

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if (! in_array($name, self::ATTRIBUTES, true)) {
                    $node->removeAttribute($attribute->name);

                    continue;
                }
                if ($name === 'style') {
                    $style = $this->sanitizeStyle($attribute->value);
                    $style === '' ? $node->removeAttribute('style') : $node->setAttribute('style', $style);
                }
            }

            if ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                if (! preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $href)) {
                    $node->removeAttribute('href');
                }
                $node->setAttribute('rel', 'noopener noreferrer');
            }

            if ($tag === 'img') {
                $src = trim($node->getAttribute('src'));
                if (! preg_match('/^(\/storage\/|data:image\/(png|jpe?g|webp);base64,)/i', $src)) {
                    $node->parentNode?->removeChild($node);

                    continue;
                }
                $node->setAttribute('style', trim($this->sanitizeStyle($node->getAttribute('style')).';max-width:100%;height:auto;', ';'));
            }

            $this->cleanChildren($node);
        }
    }

    private function sanitizeStyle(string $style): string
    {
        $clean = [];
        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, null);
            $property = strtolower(trim((string) $property));
            $value = trim((string) $value);
            if ($property === '' || $value === '' || ! in_array($property, self::STYLES, true)) {
                continue;
            }
            if (preg_match('/url\s*\(|expression|javascript:|behavior\s*:/i', $value)) {
                continue;
            }
            $clean[] = $property.':'.$value;
        }

        return implode(';', $clean);
    }

    private function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
        $this->cleanChildren($parent);
    }
}
