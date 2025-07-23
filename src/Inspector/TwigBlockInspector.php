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

namespace Machinateur\TwigBlockValidator\Inspector;

use Machinateur\TwigBlockValidator\Service\TwigBlockResolver;
use Machinateur\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Psr\EventDispatcher\EventDispatcherInterface;

class TwigBlockInspector
{
    public function __construct(
        private readonly BlockValidatorEnvironment $twig,
        private readonly TwigBlockResolver         $blockResolver,
        private readonly EventDispatcherInterface  $dispatcher,
    ) {
    }

    public function inspect(array $scopePaths, array $templatePaths = [], ?string $version = null): void
    {
        // TODO: Implement.
        //  The challenge with this will likely be getting the twig environment, node visitor and comment collection node
        //   to also collect non-hash comments as well. Filtering should be moved from the collection trait to the validator/inspector class.
    }
}
