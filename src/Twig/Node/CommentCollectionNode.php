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

namespace Machinateur\TwigBlockValidator\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * An AST representation of the block comments within a set of templates.
 *
 * The implementation logic for {@see CommentCollectionInterface} is externalized
 *  to {@see CommentCollectionTrait}, which is more reusable.
 */
#[YieldReady()]
class CommentCollectionNode extends Node implements CommentCollectionInterface
{
    use CommentCollectionTrait;

    /**
     * Disallow setting any child nodes, same as with {@see \Twig\Node\EmptyNode}.
     */
    public function setNode(string $name, Node $node): void
    {
        throw new \LogicException('ContextTagNode cannot have children.');
    }

    /**
     * Compiling a `ShopwareBlockCollectionNode` is no-op, as it will never have children. :(
     */
    public function compile(Compiler $compiler): void
    {
        // No-op.
    }
}
