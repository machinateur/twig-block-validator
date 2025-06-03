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

namespace Machinateur\TwigBlockValidator\Box\Twig\Proxy;

use Laminas\Code;
use Machinateur\TwigBlockValidator\Box\ProxyManager\ProxyGeneratorStrategyInterface;
use Machinateur\TwigBlockValidator\Box\ProxyManager\ProxyManager;
use Machinateur\TwigBlockValidator\Box\Twig\Proxy\IsolatedAbstractTwigCallableProxyTrait;
use Twig\Node\Node;
use Twig\TwigFilter;

final class IsolatedTwigFilterProxy extends TwigFilter implements ProxyGeneratorStrategyInterface
{
    use IsolatedAbstractTwigCallableProxyTrait;

    public function __construct(
        public  readonly object       $object,
        private readonly ProxyManager $proxyManager,
    ) {}

    /**
     * @param TwigFilter $object
     */
    public static function generate(object $object, Code\Generator\ClassGenerator $proxyClass, Code\Generator\FileGenerator $proxyFile): void
    {
    }

    public function getType(): string
    {
        // TODO: Implement getType() method.
    }

    public function getSafe(Node $filterArgs): ?array
    {
        // TODO: Implement getSafe() method.
    }

    public function getPreservesSafety(): array
    {
        // TODO: Implement getPreservesSafety() method.
    }

    public function getPreEscape(): ?string
    {
        // TODO: Implement getPreEscape() method.
    }

    public function getMinimalNumberOfRequiredArguments(): int
    {
        // TODO: Implement getMinimalNumberOfRequiredArguments() method.
    }
}
