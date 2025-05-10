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

namespace Machinateur\Shopware\TwigBlockValidator\Twig\Node;

/**
 * Implements {@see TwigBlockStackInterface}.
 *
 * @phpstan-import-type _LineRange from TwigBlockStackInterface
 * @phpstan-import-type _Block     from TwigBlockStackInterface
 */
trait TwigBlockStackTrait
{
    /**
     * @var array<string>
     */
    private   array   $blockStack     = [];

    /**
     * @var array<_LineRange>
     */
    private   ?array  $lines          = null;

    /**
     * @var array<string, _Block>
     */
    private   array   $blocks         = [];

    /**
     * @return string|null
     */
    public function peekBlockStack(): ?string
    {
        return $this->blockStack[\count($this->blockStack) - 1] ?? null;
    }

    /**
     * @return _LineRange|null
     */
    public function peekLines(): ?array
    {
        return $this->lines[\count($this->lines) - 1] ?? null;
    }

    public function pushBlockStack(string $name, int $lineNoStart, int $lineNoEnd): void
    {
        $this->blockStack[] = $name;
        $this->lines     [] = [$lineNoStart, $lineNoEnd];
    }

    public function popBlockStack(): void
    {
        $name  = \array_pop($this->blockStack);
        $lines = \array_pop($this->lines);

        \assert(\is_string($name));
        \assert(\is_array($lines));

        // Add block when popping it.
        $this->blocks[] = [
            'template'        => $this->getTemplate(),
            'parent_template' => $this->getParentTemplate(),
            'block'           => $name,
            'block_lines'     => $lines,
            'block_level'     => \count($this->blocks),
        ];
    }

    /**
     * @return array<_Block>
     */
    public function getBlocks(): array
    {
        // Reverse, because we only add when we exit the blocks.
        return $this->blocks;
    }
}
