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

namespace Machinateur\TwigBlockValidator\Box\Twig\Proxy\Extension;

use Laminas\Code;
use Machinateur\TwigBlockValidator\Box\ProxyManager\ProxyGeneratorStrategyInterface;
use Machinateur\TwigBlockValidator\Box\ProxyManager\ProxyManager;
use Machinateur\TwigBlockValidator\Box\Twig\Proxy\ExpressionParser\IsolatedExpressionParserProxy;
use Machinateur\TwigBlockValidator\Box\Twig\Proxy\IsolatedTwigFilterProxy;
use Machinateur\TwigBlockValidator\Box\Twig\Proxy\IsolatedTwigFunctionProxy;
use Machinateur\TwigBlockValidator\Box\Twig\Proxy\IsolatedTwigTestProxy;
use Machinateur\TwigBlockValidator\Box\Twig\Proxy\NodeVisitor\IsolatedNodeVisitorProxy;
use Machinateur\TwigBlockValidator\Box\Twig\Proxy\TokenParser\IsolatedTokenParserProxy;
use Twig\Extension\AbstractExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Extension\LastModifiedExtensionInterface;

/**
 * Wrapper class for {@see AbstractExtension} or {@see ExtensionInterface} used to mitigate type-conflicts
 *  when running from inside the executable phar archive.
 *
 * @property ExtensionInterface $extension
 */
final class IsolatedExtensionProxy implements ExtensionInterface, LastModifiedExtensionInterface, ProxyGeneratorStrategyInterface
{
    public function __construct(
        public  readonly object       $object,
        private readonly ProxyManager $proxyManager,
    ) {}

    /**
     * @param ExtensionInterface $object
     */
    public static function generate(object $object, Code\Generator\ClassGenerator $proxyClass, Code\Generator\FileGenerator $proxyFile): void
    {
        // Remove the method, if it does not exist in the object.
        if ( ! \method_exists($object, 'getExpressionParsers')) {
            $proxyClass->removeMethod('getExpressionParsers');
        }
    }

    public function getTokenParsers()
    {
        $tokenParsers = [];

        foreach ($this->extension->getTokenParsers() as $value) {
            $tokenParsers[] = $this->proxyManager->createProxy(IsolatedTokenParserProxy::class, $value);
        }

        return $tokenParsers;
    }

    public function getNodeVisitors()
    {
        $nodeVisitors = [];

        foreach ($this->extension->getNodeVisitors() as $value) {
            $nodeVisitors[] = $this->proxyManager->createProxy(IsolatedNodeVisitorProxy::class, $value);
        }

        return $nodeVisitors;
    }

    public function getFilters()
    {
        $filters = [];

        foreach ($this->extension->getFilters() as $value) {
            $filters[] = $this->proxyManager->createProxy(IsolatedTwigFilterProxy::class, $value);
        }

        return $filters;
    }

    public function getTests()
    {
        $tests = [];

        foreach ($this->extension->getTests() as $value) {
            $tests[] = $this->proxyManager->createProxy(IsolatedTwigTestProxy::class, $value);
        }

        return $tests;
    }

    public function getFunctions()
    {
        $functions = [];

        foreach ($this->extension->getFunctions() as $value) {
            $functions[] = $this->proxyManager->createProxy(IsolatedTwigFunctionProxy::class, $value);
        }

        return $functions;
    }

    public function getOperators()
    {
        $operators = [];

        foreach ($this->extension->getOperators() as $value) {
            // TODO account for any wrongly-typed array elements.
            $operators[] = $value;
        }

        return $operators;
    }

    public function getExpressionParsers(): array
    {
        $expressionParsers = [];

        // Usually this method is removed when generating the proxy class.
        if ( ! \method_exists($this->extension, 'getExpressionParsers')) {
            return $expressionParsers;
        }

        foreach ($this->extension->getExpressionParsers() as $value) {
            $expressionParsers[] = $this->proxyManager->createProxy(IsolatedExpressionParserProxy::class, $value);
        }

        return $expressionParsers;
    }

    public function getLastModified(): int
    {
        return $this->extension->getLastModified();
    }
}
