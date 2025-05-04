<?php
/*
 * MIT License
 *
 * Copyright (c) 2025 machinateur
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace Machinateur\Shopware\TwigBlockVersion\Twig\Node;

use Machinateur\Shopware\TwigBlockVersion\Twig\Extension\ShopwareBlockVersionExtension;

/**
 * An encapsulation of the {@see ShopwareBlockCollectionInterface} implementation.
 *
 * @phpstan-import-type _CommentCollection from ShopwareBlockCollectionInterface
 */
trait ShopwareBlockCollectionTrait
{
    use TwigTemplateTrait;
    use TwigBlockStackTrait;

    /**
     * @var _CommentCollection
     */
    protected array   $comments       = [];

    public function addComment(string $comment, ?string $defaultVersion = null): void
    {
        try {
            $parsed = ShopwareBlockVersionExtension::matchComment($comment);

            // Remove the full match.
            \array_shift($parsed);

            // The count is always 2, because of "unmatched as null" flag.
            \assert(2 === \count($parsed));

            [$hash, $version] = $parsed;
        } catch (\InvalidArgumentException) {
            return;
        }

        // Make sure root-level is tracked. Use non-name character to avoid collisions.
        //  The entry contains `null` for root-level comments (invalid).
        $this->comments[] = [
            'template'        => $this->getTemplate(),
            'parent_template' => $this->getParentTemplate(),
            'block'           => $this->peekBlockStack(),
            'block_lines'     => $this->peekLines(),
            'hash'            => $hash,
            'version'         => $version ?? $defaultVersion,
        ];
    }

    /**
     * @return _CommentCollection
     */
    public function getComments(): array
    {
        return $this->comments;
    }
}
