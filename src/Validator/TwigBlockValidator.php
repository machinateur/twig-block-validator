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

namespace Machinateur\TwigBlockValidator\Validator;

use Composer\Semver\Semver;
use Machinateur\TwigBlockValidator\Event\Validator\ValidateCommentsErrorEvent;
use Machinateur\TwigBlockValidator\Event\Validator\ValidateCommentsEvent;
use Machinateur\TwigBlockValidator\Service\TwigBlockResolver;
use Machinateur\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Machinateur\TwigBlockValidator\Twig\Extension\BlockVersionExtension;
use Machinateur\TwigBlockValidator\Twig\Node\CommentCollectionInterface;
use Machinateur\TwigBlockValidator\Twig\Node\TwigBlockStackInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Twig\Error\Error as TwigError;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @phpstan-import-type _Comment            from CommentCollectionInterface
 * @phpstan-import-type _CommentCollection  from CommentCollectionInterface
 * @phpstan-import-type _Block              from TwigBlockStackInterface
 *
 * @phpstan-type _ValidatedComment          _Comment&array{
 *     'source_hash'    : string,
 *     'source_version' : string,
 *     'valid'          : bool,
 * }
 * @phpstan-type _ValidatedBlockCollection  array<_ValidatedComment>
 *
 * @phpstan-import-type _NamespacedPathMap  from BlockValidatorEnvironment
 */
class TwigBlockValidator
{
    public function __construct(
        private readonly BlockValidatorEnvironment $twig,
        private readonly TwigBlockResolver         $blockResolver,
        private readonly EventDispatcherInterface  $dispatcher,
    ) {
    }

    /**
     * Validate given paths against the provided templates using the given fallback version.
     *
     * @param _NamespacedPathMap $scopePaths
     * @param _NamespacedPathMap $templatePaths
     * @param string|null        $version
     */
    public function validate(array $scopePaths, array $templatePaths = [], ?string $version = null): void
    {
        // First reset the validator's environment, in case this is called more than once in the same process.
        $this->twig->reset();

        $this->twig->registerPaths($scopePaths);
        if ($templatePaths) {
            $this->twig->registerPaths($templatePaths);
        }

        $nodeVisitor = $this->twig->getBlockNodeVisitor();
        // Get the previous default version to restore it after validation.
        $defaultVersion = $nodeVisitor->getDefaultVersion();
        $nodeVisitor->setDefaultVersion($version);

        /** @var list<TwigError> $errors */
        $errors   = [];
        $comments = $this->twig->loadComments($scopePaths, $errors);

        if (0 < \count($comments)) {
            $this->dispatcher->dispatch(
                $event = new ValidateCommentsEvent($comments, $version)
            );

            $event->notify(ValidateCommentsEvent::CALL_BEGIN);

            /** @var _ValidatedBlockCollection $comments */
            foreach ($comments as & $comment) {
                try {
                    $this->validateComment($comment, $version);

                    $event->notify(ValidateCommentsEvent::CALL_STEP, $comment);
                } catch (TwigError $error) {
                    $errors[] = $error;
                }
            }

            $event->notify(ValidateCommentsEvent::CALL_END);
        }

        if (0 < \count($errors)) {
            $this->dispatcher->dispatch(
                new ValidateCommentsErrorEvent($errors)
            );
        }

        // Reset the default version.
        $nodeVisitor->setDefaultVersion($defaultVersion);
    }

    /**
     * Validate a single comment for a block. Shortcut method, internal logic.
     *
     * @param _Comment|_ValidatedComment $comment
     *
     * @throws LoaderError      when the block cannot be resolved or the template does not exist
     * @throws RuntimeError     when the generated code is erroneous
     * @throws SyntaxError      when there is a syntax error, like missing tags
     */
    public function validateComment(array & $comment, string $defaultVersion): bool
    {
        $template       = $comment['template'];
        $blockName      = $comment['block'];
        $hash           = $comment['hash'];
        $version        = $comment['version'];

        // Resolve the template block in hierarchy.
        $parentBlock = $this->blockResolver->resolveParentBlock($template, $blockName);
        if (null !== $parentBlock) {
            // Get source code of the parent block.
            $sourceCode  = $this->getBlockContent($parentBlock);
            $sourceHash  = BlockVersionExtension::hash($sourceCode);
        } else {
            $sourceHash  = '--';
        }
        // Enrich the comment, i.e. _ValidatedComment.
        $comment['source_hash']    = $sourceHash;
        $comment['source_version'] = $defaultVersion;
        $comment['valid']          = false;

        $matchHash    = $hash === $sourceHash;
        $matchVersion = Semver::satisfies($version, '~'.$defaultVersion);

        $comment['match'] = [
            'hash'    => $matchHash,
            'version' => $matchVersion,
        ];

        return $comment['valid'] = ($matchHash && $matchVersion);
    }

    /**
     * Extract the block content from the template's source.
     *
     * @param _Block $block
     *
     * @throws LoaderError      when the template does not exist
     * @throws SyntaxError      when the block start or end tag cannot be found
     *
     * @phpstan-type _MatchWithOffset   array<int, array{0:string,1:int}>
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
            // {#
            \preg_quote($blockTags[0], '#'),
            // $blockName
            \preg_quote($blockName, '#'),
            // #}
            \preg_quote($blockTags[1], '#'),
        ];

        // {# block (name) #}
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
