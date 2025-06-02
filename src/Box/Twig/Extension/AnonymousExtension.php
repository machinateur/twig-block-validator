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

namespace Machinateur\TwigBlockValidator\Box\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\Extension\ExtensionInterface;

/**
 * Wrapper class for {@see AbstractExtension} or {@see ExtensionInterface} used to mitigate type-conflicts.
 *
 * TODO: Wrap extension specifics.
 *
 * @property ExtensionInterface $extension
 */
class AnonymousExtension extends AbstractExtension
{
    protected function __construct(
        public readonly object $extension,
    ) {}

    public function getTokenParsers()
    {
        $tokenParsers = [];

        foreach ($this->extension->getTokenParsers() as $value) {
            $tokenParsers[] = $value;
        }

        return $tokenParsers;
    }

    public function getNodeVisitors()
    {
        $nodeVisitors = [];

        foreach ($this->extension->getNodeVisitors() as $value) {
            $nodeVisitors[] = $value;
        }

        return $nodeVisitors;
    }

    public function getFilters()
    {
        $filters = [];

        foreach ($this->extension->getFilters() as $value) {
            $filters[] = $value;
        }

        return $filters;
    }

    public function getTests()
    {
        $tests = [];

        foreach ($this->extension->getTests() as $value) {
            $tests[] = $value;
        }

        return $tests;
    }

    public function getFunctions()
    {
        $functions = [];

        foreach ($this->extension->getFunctions() as $value) {
            $functions[] = $value;
        }

        return $functions;
    }

    public function getOperators()
    {
        $operators = [];

        foreach ($this->extension->getOperators() as $value) {
            $operators[] = $value;
        }

        return $operators;
    }

    public function getExpressionParsers(): array
    {
        $expressionParsers = [];

        foreach ($this->extension->getExpressionParsers() as $value) {
            $expressionParsers[] = $value;
        }

        return $expressionParsers;
    }

    public function getLastModified(): int
    {
        return $this->extension->getLastModified();
    }
}
