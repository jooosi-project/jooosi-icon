<?php

declare (strict_types=1);
namespace JooosiIcon\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use JooosiIconDeps\enshrined\svgSanitize\Sanitizer;
use JooosiIcon\Core\Discovery\Attributes\Service;
/**
 * Sanitizes SVG markup while preserving safe SMIL animations.
 */
#[Service]
final class SvgSanitizer
{
    private const ANIMATION_ELEMENTS = ['animate', 'animatecolor', 'animatemotion', 'animatetransform', 'set'];
    /**
     * Animation targets that affect SVG appearance without changing links,
     * event handlers, or document structure.
     */
    private const SAFE_ANIMATION_TARGETS = ['alignment-baseline', 'baseline-shift', 'clip-path', 'clip-rule', 'color', 'color-interpolation', 'color-rendering', 'cx', 'cy', 'd', 'display', 'dominant-baseline', 'dx', 'dy', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'flood-color', 'flood-opacity', 'font-family', 'font-size', 'font-size-adjust', 'font-stretch', 'font-style', 'font-variant', 'font-weight', 'height', 'lighting-color', 'letter-spacing', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'opacity', 'paint-order', 'points', 'r', 'rotate', 'rx', 'ry', 'shape-rendering', 'stop-color', 'stop-opacity', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'text-anchor', 'text-decoration', 'text-rendering', 'transform', 'vector-effect', 'viewbox', 'visibility', 'width', 'x', 'x1', 'x2', 'y', 'y1', 'y2'];
    private Sanitizer $sanitizer;
    public function __construct()
    {
        $this->sanitizer = new Sanitizer();
        $this->sanitizer->setAllowedTags(new \JooosiIcon\Services\SvgSanitizerAllowedTags());
        $this->sanitizer->setAllowedAttrs(new \JooosiIcon\Services\SvgSanitizerAllowedAttributes());
        // SVG is embedded as HTML, so avoid emitting an XML declaration.
        $this->sanitizer->removeXMLTag(\true);
    }
    public function sanitize(string $svg): string|false
    {
        $sanitized = $this->sanitizer->sanitize($svg);
        if ($sanitized === \false || $sanitized === '') {
            return \false;
        }
        return $this->removeUnsafeAnimations($sanitized);
    }
    private function removeUnsafeAnimations(string $svg): string|false
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = \false;
        $document->formatOutput = \true;
        $previousErrorMode = libxml_use_internal_errors(\true);
        $loaded = $document->loadXML($svg, \LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);
        if (!$loaded || !$document->documentElement instanceof DOMElement) {
            return \false;
        }
        $xpath = new DOMXPath($document);
        $elements = $xpath->query('//*');
        if ($elements === \false) {
            return \false;
        }
        foreach (iterator_to_array($elements, \false) as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }
            $tagName = strtolower($element->localName ?: $element->tagName);
            if ($tagName === 'mpath') {
                if (!$this->hasSafeLocalHref($element)) {
                    $element->parentNode?->removeChild($element);
                }
                continue;
            }
            if (in_array($tagName, self::ANIMATION_ELEMENTS, \true) && $this->hasUnsafeAnimation($element, $tagName)) {
                $element->parentNode?->removeChild($element);
            }
        }
        $clean = $document->saveXML($document->documentElement);
        return is_string($clean) && $clean !== '' ? $clean : \false;
    }
    private function hasUnsafeAnimation(DOMElement $animation, string $tagName): bool
    {
        $target = strtolower(trim($animation->getAttribute('attributeName')));
        if (in_array($tagName, ['animate', 'animatecolor', 'animatetransform', 'set'], \true)) {
            if ($target === '' || !in_array($target, self::SAFE_ANIMATION_TARGETS, \true)) {
                return \true;
            }
        } elseif ($target !== '' && !in_array($target, self::SAFE_ANIMATION_TARGETS, \true)) {
            return \true;
        }
        $attributeType = strtolower(trim($animation->getAttribute('attributeType')));
        if ($attributeType !== '' && !in_array($attributeType, ['auto', 'css', 'xml'], \true)) {
            return \true;
        }
        if ($tagName === 'animatetransform' && $target !== 'transform') {
            return \true;
        }
        if (!$this->hasSafeLocalHref($animation)) {
            return \true;
        }
        foreach (['values', 'from', 'to', 'by', 'path'] as $attributeName) {
            if ($animation->hasAttribute($attributeName) && $this->hasUnsafeAnimationValue($animation->getAttribute($attributeName))) {
                return \true;
            }
        }
        return \false;
    }
    private function hasSafeLocalHref(DOMElement $element): bool
    {
        foreach ($element->attributes as $attribute) {
            $attributeName = strtolower($attribute->nodeName);
            if ($attributeName !== 'href' && $attributeName !== 'xlink:href') {
                continue;
            }
            // Animation target references and motion paths must stay inside this SVG.
            if (!str_starts_with($attribute->value, '#') || $attribute->value === '#' || preg_match('/[\x00-\x20]/', $attribute->value) === 1) {
                return \false;
            }
        }
        return \true;
    }
    private function hasUnsafeAnimationValue(string $value): bool
    {
        $normalized = preg_replace('/[\x00-\x20]+/', '', $value);
        if ($normalized === null || preg_match('/\b[a-z][a-z0-9+.-]*:/i', $normalized) === 1 || str_contains($value, '\\') || str_contains($value, '/*') || str_contains($value, '*/')) {
            return \true;
        }
        // Keep same-document paint-server references, but reject remote or malformed URLs.
        $withoutLocalReferences = preg_replace('~url\(\s*(?:"#[^"]+"|\'#[^\']+\'|#[^)\s]+)\s*\)~i', '', $value);
        return $withoutLocalReferences === null || preg_match('/\burl\s*\(/i', $withoutLocalReferences) === 1;
    }
}
