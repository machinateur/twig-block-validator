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

namespace Machinateur\TwigBlockValidator\Box\Twig\Proxy\NodeVisitor;

use Machinateur\TwigBlockValidator\Box\Twig\Proxy\ProxyManager;
use Twig\Environment;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final class IsolatedNodeVisitorProxy implements NodeVisitorInterface
{
    public function __construct(
        public  readonly object       $object,
        private readonly ProxyManager $proxyManager,
    ) {}

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node, Environment $env): Node
    {
        // TODO: Implement enterNode() method.
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node, Environment $env): ?Node
    {
        // TODO: Implement leaveNode() method.
    }

    /**
     * @inheritDoc
     */
    public function getPriority()
    {
        // TODO: Implement getPriority() method.
    }
}
