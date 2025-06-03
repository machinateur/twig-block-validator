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

use Twig\TwigCallableInterface;

/**
 * Common proxy definition for {@see \Twig\AbstractTwigCallable}.
 *
 * @implements TwigCallableInterface
 */
trait IsolatedAbstractTwigCallableProxyTrait
{
    public function __toString(): string
    {
        // TODO: Implement __toString() method.
    }

    public function getName(): string
    {
        // TODO: Implement getName() method.
    }

    public function getDynamicName(): string
    {
        // TODO: Implement getDynamicName() method.
    }

    /**
     * @return callable|array{class-string, string}|null
     */
    public function getCallable()
    {
        // TODO: Implement getCallable() method.
    }

    public function getNodeClass(): string
    {
        // TODO: Implement getNodeClass() method.
    }

    public function needsCharset(): bool
    {
        // TODO: Implement needsCharset() method.
    }

    public function needsEnvironment(): bool
    {
        // TODO: Implement needsEnvironment() method.
    }

    public function needsContext(): bool
    {
        // TODO: Implement needsContext() method.
    }

    /**
     * @return static
     */
    public function withDynamicArguments(string $name, string $dynamicName, array $arguments): self
    {
        // TODO: Implement withDynamicArguments() method.
    }

    /**
     * @deprecated since Twig 3.12, use withDynamicArguments() instead
     */
    public function setArguments(array $arguments): void
    {
        // TODO: Implement setArguments() method.
    }

    public function getArguments(): array
    {
        // TODO: Implement getArguments() method.
    }

    public function isVariadic(): bool
    {
        // TODO: Implement isVariadic() method.
    }

    public function isDeprecated(): bool
    {
        // TODO: Implement isDeprecated() method.
    }

    public function triggerDeprecation(?string $file = null, ?int $line = null): void
    {
        // TODO: Implement triggerDeprecation() method.
    }

    /**
     * @deprecated since Twig 3.15
     */
    public function getDeprecatingPackage(): string
    {
        // TODO: Implement getDeprecatingPackage() method.
    }

    /**
     * @deprecated since Twig 3.15
     */
    public function getDeprecatedVersion(): string
    {
        // TODO: Implement getDeprecatedVersion() method.
    }

    /**
     * @deprecated since Twig 3.15
     */
    public function getAlternative(): ?string
    {
        // TODO: Implement getAlternative() method.
    }

    public function getMinimalNumberOfRequiredArguments(): int
    {
        // TODO: Implement getMinimalNumberOfRequiredArguments() method.
    }
}
