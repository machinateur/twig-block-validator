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

namespace Machinateur\TwigBlockValidator\Twig;

use Twig\Node\BlockNode;
use Twig\Node\BodyNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Parser;
use Twig\TokenStream;

class BlockStackParser extends Parser
{
    public function parse(TokenStream $stream, $test = null, bool $dropNeedle = false): ModuleNode
    {
        // TODO: Investigate missing "content" block when annotating "tests/templates/twig-embed/page.html.twig".
        // TODO: Investigate duplicated "top" block when annotating "tests/templates/twig-embed/page.html.twig".

        return parent::parse($stream, $test, $dropNeedle);
    }

    /**
     * @param string $name
     */
    public function pushBlockStack($name): void
    {
        parent::pushBlockStack($name);

        $currentBlock = $this->peekBlockStack();

        if (null === $currentBlock) {
            throw new \UnexpectedValueException('Cannot push block stack without current block!');
        }

        $token = $this->getCurrentToken();
        /** @var BodyNode  $body */
        $body  = $this->getBlock($currentBlock);
        /** @var BlockNode $block */
        $block = $body->getNode('0');
        $block->setAttribute('line_no_start', $token->getLine());
    }

    public function popBlockStack(): void
    {
        $currentBlock = $this->peekBlockStack();

        if (null === $currentBlock) {
            throw new \UnexpectedValueException('Cannot pop block stack without current block!');
        }

        parent::popBlockStack();

        $token = $this->getCurrentToken();
        /** @var BodyNode  $body */
        $body  = $this->getBlock($currentBlock);
        /** @var BlockNode $block */
        $block = $body->getNode('0');
        $block->setAttribute('line_no_end', $token->getLine());
    }

    public function getBlock(string $name): Node
    {
        static $getBlocks;
        $getBlocks ??= \Closure::bind(static fn (Parser $parser): array => $parser->blocks, null, Parser::class);

        $blocks = $getBlocks($this, $name);
        if ( ! isset($blocks[$name])) {
            throw new \InvalidArgumentException(\sprintf('Block named "%s" does not exist.', $name));
        }
        return $blocks[$name];
    }
}
