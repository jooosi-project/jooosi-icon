<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiIconDeps\Symfony\Component\Mime\Encoder;

/**
 * @author Chris Corbyn
 */
final class QpMimeHeaderEncoder extends QpEncoder implements MimeHeaderEncoderInterface
{
    protected function initSafeMap(): void
    {
        foreach (array_merge(range(0x61, 0x7a), range(0x41, 0x5a), range(0x30, 0x39), [0x20, 0x21, 0x2a, 0x2b, 0x2d, 0x2f]) as $byte) {
            $this->safeMap[$byte] = \chr($byte);
        }
    }
    public function getName(): string
    {
        return 'Q';
    }
    public function encodeString(string $string, ?string $charset = 'utf-8', int $firstLineOffset = 0, int $maxLineLength = 0): string
    {
        return str_replace([' ', '=20', "=\r\n"], ['_', '_', "\r\n"], parent::encodeString($string, $charset, $firstLineOffset, $maxLineLength));
    }
}
