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

namespace Machinateur\TwigBlockValidator\Command;

use Machinateur\TwigBlockValidator\Annotator\TwigBlockAnnotator;
use Machinateur\TwigBlockValidator\Annotator\TwigBlockAnnotatorOutput;
use Symfony\Component\Console\Command\Command;

class TwigBlockAnnotateCommand extends Command
{
    // TODO: Implement annotate service infrastructure, to eventually support scanning whole twig code-bases.

    public const DEFAULT_NAME = 'twig:block:annotate';

    /**
     * @param string|null $name     The override command name. This is useful for adding it as composer script.
     */
    public function __construct(
        private readonly TwigBlockAnnotator       $validator,
        private readonly TwigBlockAnnotatorOutput $output,
        ?string                                   $name = null,
    ) {
        parent::__construct($name);
    }

    public static function getDefaultName(): ?string
    {
        return self::DEFAULT_NAME;
    }
}
