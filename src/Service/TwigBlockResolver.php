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
use Machinateur\TwigBlockValidator\Twig\Extension\BlockValidatorExtension;
use Machinateur\TwigBlockValidator\Twig\Node\TwigBlockStackInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @phpstan-type _MatchWithOffset           array<int, array{0:string,1:int}>
 *
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
    public function resolveBlock(string $template, string $blockName): ?array
    {
        $this->twig->load($template);

        $blocks   = $this->twig->getBlocks($template);

        return $blocks[$blockName] ?? null;
    }

    /**
     * Resolve a given template and block name combination to a block struct of the top-most ancestor block (or self).
     *
     * @return _Block|null
     *
     * @throws LoaderError      when the block cannot be resolved or the template does not exist
     * @throws RuntimeError     when the generated code is erroneous
     * @throws SyntaxError      when there is a syntax error, like missing tags
     */
    public function resolveParentBlock(string $template, string $blockName): ?array
    {
        $templates = [];
        do {
            $block = $this->resolveBlock($template, $blockName);

            if ( ! isset($block['parent_template'])) {
                break;
            }

            if ($template === $block['parent_template']) {
                // Infinite loop detected.
                break;
            }

            $template    = $block['parent_template'];

            if (\in_array($template, $templates, true)) {
                throw new LoaderError(\sprintf('Recursion error resolving "%s" ("%s")', $template, \implode('", "', $templates)));
            }

            $templates[] = $template;
        } while (null !== $block);

        return $block;
    }

    /**
     * Generate the source hash of the given block's ancestor (origin block).
     *
     * @throws LoaderError      when the template does not exist
     * @throws RuntimeError     when the generated code is erroneous or recursion is detected
     * @throws SyntaxError      when the block start or end tag cannot be found
     */
    public function getSourceHash(string $template, string $blockName): ?string
    {
        // Resolve the template block in hierarchy.
        //  In case of `sw_extends` this also works fine, because the top-most block is resolved (i.e. `@Storefront`).
        try {
            $parentBlock = $this->resolveParentBlock($template, $blockName);
        } catch (LoaderError) {
            $parentBlock = null;
        }
        if (null === $parentBlock) {
            return null;
        }

        // Get source code of the parent block.
        $sourceCode  = $this->getBlockContent($parentBlock);

        return BlockValidatorExtension::hash($sourceCode);
    }

    /**
     * Extract the block content from the template's source.
     *
     * @param _Block $block
     *
     * @throws LoaderError      when the template does not exist
     * @throws SyntaxError      when the block start or end tag cannot be found
     */
    protected function getBlockContent(array $block): string
    {
        // Prepare required variables from the given block.
        $template                          = $block['template'];
        $blockName                         = $block['block'];
        [$blockLinesStart, $blockLinesEnd] = $block['block_lines'];

        // Prepare offset (arrays are zero-indexed, lines are not).
        --$blockLinesStart;
        --$blockLinesEnd;

        // Calculate number of lines spanned by the block.
        $blockLineCount  = $blockLinesEnd - $blockLinesStart;
        // Get the source contents and full source code of the template.
        $sourceContext   = $this->twig->getLoader()
            ->getSourceContext($template);
        $sourceCode      = $sourceContext->getCode();
        // Slice the portion of lines that are needed.
        $sourceCodeLines = \array_slice(\explode("\n", $sourceCode), $blockLinesStart, $blockLineCount);

        // Extract first and last line by reference from the block's source code lines array.
        $firstLine = & $sourceCodeLines[0];
        $lastLine  = & $sourceCodeLines[$blockLineCount - 1];

        $blockTags = $this->twig->getLexerOptions()['tag_block'];

        // Define the parameters to use for regex inception.
        $params = [
            // {%
            \preg_quote($blockTags[0], '#'),
            // $blockName
            \preg_quote($blockName, '#'),
            // %}
            \preg_quote($blockTags[1], '#'),
        ];

        // {% block (name) %}
        $firstLinePattern = \vsprintf('{%s\s*block\s+(?:%s)\s*%s}sx', $params);
        if (1 !== \preg_match($firstLinePattern, $firstLine, $firstLineMatch, flags: \PREG_OFFSET_CAPTURE)) {
            throw new SyntaxError(\sprintf('The start tag for block "%s" was not found.', $blockName), $blockLinesStart, $sourceContext);
        }

        // {% endblock (name)? %}
        $lastLinePattern  = \vsprintf('{%s\s*endblock(?:\s+%s)?\s*%s}sx', $params);
        if (1 !== \preg_match($lastLinePattern, $lastLine, $lastLineMatch, flags: \PREG_OFFSET_CAPTURE)) {
            throw new SyntaxError(\sprintf('The end tag for block "%s" was not found.', $blockName), $blockLinesStart + $blockLineCount, $sourceContext);
        }

        // Assign new first and last line to offset substring.
        /** @var _MatchWithOffset $firstLineMatch */
        $firstLine = \substr($firstLine, $firstLineMatch[0][1]);
        /** @var _MatchWithOffset $lastLineMatch */
        $lastLine  = \substr($lastLine, $lastLineMatch[0][1]);

        // Return the combined source code lines array as string. Newline is normalized to "\n" (twig default).
        return \implode("\n", $sourceCodeLines);
    }
}
