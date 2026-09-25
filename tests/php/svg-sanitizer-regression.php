<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use JooosiIcon\Services\SvgSanitizer;

function expectContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function expectNotContains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

$sanitizer = new SvgSanitizer();

$lineMdDownloadingLoop = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
  <g fill="none" stroke="currentColor" stroke-width="2">
    <path stroke-dasharray="32" d="M12 21c-4.97 0 -9 -4.03 -9 -9c0 -4.97 4.03 -9 9 -9">
      <animate fill="freeze" attributeName="stroke-dashoffset" dur="0.6s" values="32;0"/>
    </path>
    <path stroke-dasharray="2 4" stroke-dashoffset="6" d="M12 3c4.97 0 9 4.03 9 9c0 4.97 -4.03 9 -9 9" opacity="0">
      <set fill="freeze" attributeName="opacity" begin="0.45s" to="1"/>
      <animateTransform fill="freeze" attributeName="transform" begin="0.45s" dur="0.6s" type="rotate" values="-180 12 12;0 12 12"/>
      <animate attributeName="stroke-dashoffset" begin="0.85s" dur="0.6s" repeatCount="indefinite" to="0"/>
    </path>
    <circle cx="12" cy="12" r="3">
      <animate attributeName="opacity" from="0" to="1" calcMode="linear" additive="replace" dur="0.4s"/>
    </circle>
  </g>
</svg>
SVG;

$cleanLineMd = $sanitizer->sanitize($lineMdDownloadingLoop);
if (!is_string($cleanLineMd)) {
    throw new RuntimeException('The Line MD animated SVG should sanitize successfully.');
}

foreach ([
    '<animate ',
    '<set ',
    '<animateTransform ',
    'to="1"',
    'to="0"',
    'from="0"',
    'calcMode="linear"',
    'additive="replace"',
] as $expected) {
    expectContains($expected, $cleanLineMd, sprintf('Expected sanitized SVG to preserve %s.', $expected));
}

$animatedHrefPayload = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
  <a href="#safe">
    <text x="0" y="12">click</text>
    <animate attributeName="href" values="#safe;javascript:alert(1)" dur="0.01s" fill="freeze"/>
  </a>
  <circle cx="12" cy="12" r="4">
    <animate attributeName="opacity" values="0;1" dur="1s" repeatCount="indefinite"/>
  </circle>
</svg>
SVG;

$cleanHrefPayload = $sanitizer->sanitize($animatedHrefPayload);
if (!is_string($cleanHrefPayload)) {
    throw new RuntimeException('The SVG containing an unsafe animation should still sanitize.');
}
expectNotContains('javascript:', $cleanHrefPayload, 'Unsafe animated href values must be removed.');
expectNotContains('<animate attributeName="href"', $cleanHrefPayload, 'Animations targeting href must be removed.');
expectContains('<animate attributeName="opacity"', $cleanHrefPayload, 'Safe visual animations should remain.');

$remotePaintValue = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
  <circle cx="12" cy="12" r="4">
    <animate attributeName="fill" values="red;url(https://example.com/paint.svg#fill)" dur="1s"/>
  </circle>
</svg>
SVG;

$cleanRemotePaintValue = $sanitizer->sanitize($remotePaintValue);
if (!is_string($cleanRemotePaintValue)) {
    throw new RuntimeException('The SVG containing an external animated paint value should still sanitize.');
}
expectNotContains('https://example.com/paint.svg', $cleanRemotePaintValue, 'External URLs in animation values must be removed.');

$remoteMotionPath = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24">
  <circle cx="2" cy="2" r="1">
    <animateMotion dur="1s" repeatCount="indefinite"><mpath xlink:href="https://example.com/path.svg#motion"/></animateMotion>
  </circle>
</svg>
SVG;

$cleanMotionPath = $sanitizer->sanitize($remoteMotionPath);
if (!is_string($cleanMotionPath)) {
    throw new RuntimeException('The SVG containing a remote motion path should still sanitize.');
}
expectNotContains('https://example.com/path.svg', $cleanMotionPath, 'Remote motion path references must be removed.');

$localMotionPath = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24">
  <defs><path id="motion" d="M0 0 L10 10"/></defs>
  <circle cx="2" cy="2" r="1">
    <animateMotion dur="1s" repeatCount="indefinite"><mpath xlink:href="#motion"/></animateMotion>
  </circle>
</svg>
SVG;

$cleanLocalMotionPath = $sanitizer->sanitize($localMotionPath);
if (!is_string($cleanLocalMotionPath)) {
    throw new RuntimeException('The SVG containing a local motion path should sanitize successfully.');
}
expectContains('<mpath xlink:href="#motion"', $cleanLocalMotionPath, 'Local motion path references should remain available.');

fwrite(STDOUT, "SVG sanitizer regression checks passed.\n");
