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
use Twig\Template;
use Twig\TemplateWrapper;

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
     * Resolve a given template and block name combination to a block struct of the furthest direct ancestor block.
     *
     * Notice that this is not the same as the origin-block,
     *  because a `template.html.twig` -> `intermediate_1.html.twig` -> `intermediate_2.html.twig` -> `origin.html.twig`
     *   structure, where `intermediate_2.html.twig` does not contain the block (i.e. extends it),
     *    will resolve to `intermediate_1.html.twig`.
     *
     * @return _Block|null
     *
     * @throws LoaderError      when the block cannot be resolved or the template does not exist
     * @throws RuntimeError     when the generated code is erroneous
     * @throws SyntaxError      when there is a syntax error, like missing tags
     *
     * @deprecated No longer used. Use {@see TwigBlockResolver::resolveOriginBlock()} instead.
     */
    public function resolveParentBlock(string $template, string $blockName): ?array
    {
        $originalTemplate = $template;
        $templates        = [$originalTemplate];

        do {
            $block = $this->resolveBlock($template, $blockName);

            if ( ! isset($block['parent_template'])) {
                break;
            }

            if ($template === $block['parent_template']) {
                // Infinite loop detected, self-reference.
                break;
            }

            $template    = $block['parent_template'];

            if (\in_array($template, $templates, true)) {
                // Infinite loop detected, alternating through parents.
                throw new LoaderError(\sprintf('Recursion error resolving "%s" ("%s")', $template, \implode('", "', $templates)));
            }

            $templates[] = $template;
        } while (null !== $block);

        if ($block && ($block['template'] === $originalTemplate) && $block['block'] === $blockName) {
            // Cannot return the same template as parent, as was given for resolution.
            return null;
        }

        return $block;
    }

    /**
     * Resolve a given template and block name combination to a block struct of the top-most ancestor block.
     *
     * @return _Block|null
     *
     * @throws LoaderError      when the block cannot be resolved or the template does not exist
     * @throws RuntimeError     when the generated code is erroneous
     * @throws SyntaxError      when there is a syntax error, like missing tags
     */
    public function resolveOriginBlock(string $template, string $blockName): ?array
    {
        $originalTemplate = $template;
        $templates        = [$originalTemplate];
        $originBlock      = null;

        do {
            $wrapper = $this->twig->load($template);
            $blocks  = $this->twig->getBlocks($template);

            if (isset($blocks[$blockName])) {
                $originBlock = $blocks[$blockName];
            }

            // Set to parent (false if no parent).
            $template = $wrapper->unwrap()
                ->getParent([]);

            // In case it's loaded (but likely not in cache).
            if ($template instanceof TemplateWrapper || $template instanceof Template) {
                $template = $template->getSourceContext()
                    ->getName();
            }

            // Could still be `false`.
            if (\is_string($template)) {
                if (\in_array($template, $templates, true)) {
                    // Infinite loop detected, alternating through parents.
                    throw new LoaderError(\sprintf('Recursion error resolving "%s" ("%s")', $template, \implode('", "', $templates)));
                }

                $templates[] = $template;
            }
        } while (false !== $template);

        if ($originBlock && ($originBlock['template'] === $originalTemplate) && $originBlock['block'] === $blockName) {
            // Cannot return the same template as parent, as was given for resolution.
            return null;
        }

        return $originBlock;
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
        $parentBlock = $this->resolveOriginBlock($template, $blockName);

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
        $sourceCodeLines = \explode("\n", $sourceCode);

        // Handle special case, where the block is "inline".
        if ($blockLinesStart === $blockLinesEnd || 0 === $blockLineCount) {
            $sourceCodeLines = [$sourceCodeLines[$blockLinesStart]];
            $blockLineCount  = \count($sourceCodeLines);
        } else {
            // Slice the portion of lines that are needed.
            $sourceCodeLines = \array_slice($sourceCodeLines, $blockLinesStart, $blockLineCount);
        }

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
        /** @var _MatchWithOffset $firstLineMatch */

        // {% endblock (name)? %}
        $lastLinePattern  = \vsprintf('{%s\s*endblock(?:\s+%s)?\s*%s}sx', $params);
        if (1 !== \preg_match($lastLinePattern, $lastLine, $lastLineMatch, flags: \PREG_OFFSET_CAPTURE)) {
            throw new SyntaxError(\sprintf('The end tag for block "%s" was not found.', $blockName), $blockLinesStart + $blockLineCount, $sourceContext);
        }
        /** @var _MatchWithOffset $lastLineMatch */

        if (1 === $blockLineCount) {
            // If there is only one line, first and last line refer to the same line. Only update that.
            $sourceCodeLines[0] = \substr($sourceCodeLines[0], $firstLineMatch[0][1], $lastLineMatch[0][1]);
        } else {
            // Assign new first and last line to offset substring.
            $firstLine = \substr($firstLine, $firstLineMatch[0][1]);
            $lastLine  = \substr($lastLine, $lastLineMatch[0][1]);
        }

        // Return the combined source code lines array as string. Newline is normalized to "\n" (twig default).
        return \implode("\n", $sourceCodeLines);
    }
}
