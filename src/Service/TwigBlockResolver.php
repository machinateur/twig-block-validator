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

namespace Machinateur\TwigBlockValidator\Service;

use Machinateur\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Machinateur\TwigBlockValidator\Twig\Node\TwigBlockStackInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @phpstan-import-type _Block              from TwigBlockStackInterface
 */
class TwigBlockResolver
{
    public function __construct(
        private readonly BlockValidatorEnvironment $twig,
    ) {
    }

    /**
     * Resolve a given template and block name combination to a block struct.
     *
     * @return _Block|null
     *
     * @throws LoaderError      when the block cannot be resolved or the template does not exist
     * @throws RuntimeError     when the generated code is erroneous
     * @throws SyntaxError      when there is a syntax error, like missing tags
     */
    public function resolveParentBlock(string $template, string $blockName): ?array
    {
        $this->twig->load($template);

        $originalTemplate = $template;
        do {
            $blocks   = $this->twig->getBlocks($template);
            $block    = $blocks[$blockName] ?? null;
            if ( ! isset($block['parent_template'])) {
                break;
            }

            $template = $block['parent_template'];
            $this->twig->load($template);
        } while (null !== $block);

        if (null === $block) {
            throw new LoaderError(\sprintf('The block "%s" was not found in template "%s" (or ancestors).', $blockName, $originalTemplate));
        }

        return $block;
    }
}
