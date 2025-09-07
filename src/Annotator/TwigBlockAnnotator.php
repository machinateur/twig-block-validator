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

namespace Machinateur\TwigBlockValidator\Annotator;

use Machinateur\TwigBlockValidator\Event\Annotator\AnnotateBlocksErrorEvent;
use Machinateur\TwigBlockValidator\Event\Annotator\AnnotateBlocksEvent;
use Machinateur\TwigBlockValidator\Service\TwigBlockResolver;
use Machinateur\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Machinateur\TwigBlockValidator\Twig\Extension\BlockValidatorExtension;
use Machinateur\TwigBlockValidator\Twig\Node\CommentCollectionInterface;
use Machinateur\TwigBlockValidator\Twig\Node\TwigBlockStackInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Error\Error as TwigError;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Source as TwigSource;

/**
 * @phpstan-import-type _Comment            from CommentCollectionInterface
 * @phpstan-import-type _CommentCollection  from CommentCollectionInterface
 * @phpstan-import-type _Block              from TwigBlockStackInterface
 *
 * @phpstan-type _AnnotatedBlock            _Block&array{
 *     'source_hash'    : string|null,
 *     'source_version' : string|null,
 *     'created'        : bool,
 * }
 * @phpstan-type _AnnotatedBlockCollection  array<_AnnotatedBlock>
 *
 * @phpstan-import-type _NamespacedPathMap  from BlockValidatorEnvironment
 * @phpstan-import-type _MatchWithOffset    from TwigBlockResolver
 */
class TwigBlockAnnotator implements ResetInterface
{
    /**
     * @var array<string, int>
     */
    private array   $offsets = [];

    public function __construct(
        private readonly BlockValidatorEnvironment $twig,
        private readonly TwigBlockResolver         $blockResolver,
        private readonly EventDispatcherInterface  $dispatcher,
    ) {
    }

    protected function getOffset(string $template): int
    {
        // Get offset.
        return $this->offsets[$template] ?? 0;
    }

    protected function hasOffset(string $template): bool
    {
        return 0 === $this->getOffset($template);
    }

    protected function addOffset(string $template, int $offset = 1): void
    {
        // Make sure offset record exists.
        $this->offsets[$template] ??= 0;

        // Add offset, if exists.
        $this->offsets[$template] += $offset;
    }

    public function annotate(array $scopePaths, array $templatePaths = [], ?string $version = null): void
    {
        $scopePaths    = \array_map('array_unique', $scopePaths);
        $templatePaths = \array_map('array_unique', $templatePaths);

        // First reset the validator's environment, in case this is called more than once in the same process.
        $this->twig->reset();

        $this->twig->registerPaths($scopePaths);
        if ($templatePaths) {
            $this->twig->registerPaths($templatePaths);
        }

        $nodeVisitor = $this->twig->getBlockNodeVisitor();
        // Get the previous default version to restore it after annotation.
        $defaultVersion = $nodeVisitor->getDefaultVersion();
        $nodeVisitor->setDefaultVersion($version);

        /** @var list<TwigError> $errors */
        $errors   = [];
        $blocks   = $this->twig->loadBlocks($scopePaths, $errors);
        $comments = $this->twig->loadComments($scopePaths, $errors);

        if (0 < \count($blocks)) {
            /** @var array<string, _Comment> $groupedComments */
            $groupedComments = \array_reduce($comments, static function (array $groupedComments, array $comment): array {
                $groupedComments[$comment['template']][$comment['block']] = $comment;
                return $groupedComments;
            }, []);

            $this->dispatcher->dispatch(
                $event = new AnnotateBlocksEvent($blocks, $groupedComments, $version)
            );

            $event->notify(AnnotateBlocksEvent::CALL_BEGIN);

            /** @var _Block[] $blocks */
            foreach ($blocks as & $block) {
                $comment = $groupedComments[$block['template']][$block['block']] ?? null;

                try {
                    $this->processBlock($block, $version, $comment);

                    $event->notify(AnnotateBlocksEvent::CALL_STEP, $block, $comment);
                } catch (TwigError $error) {
                    $errors[] = $error;
                }
            }

            $event->notify(AnnotateBlocksEvent::CALL_END);
        }

        if (0 < \count($errors)) {
            $this->dispatcher->dispatch(
                new AnnotateBlocksErrorEvent($errors)
            );
        }

        // Reset the default version.
        $nodeVisitor->setDefaultVersion($defaultVersion);
    }


    /**
     * @param _Block $block
     *
     * @throws SyntaxError
     * @throws LoaderError
     * @throws RuntimeError
     */
    protected function processBlock(array & $block, ?string $defaultVersion, ?array $comment): void
    {
        $created        = (null === $comment);
        $template       = $block['template'];
        $blockName      = $block['block'];

        // Enrich the block, i.e. _AnnotatedBlock.
        if ( ! isset($block['parent_template'])) {
            $block['created']        = false;
            $block['source_hash']    = null;
            $block['source_version'] = $defaultVersion;

            // No parent block - nothing to do.
            return;
        }

        $block['source_hash']    = $this->blockResolver->getSourceHash($template, $blockName);
        $block['source_version'] = $defaultVersion;
        $block['created']        = $created && null !== $block['source_hash'];

        if (null === $block['source_hash']) {
            // No parent block was found, skip.
            return;
        }

        // Get the source contents and full source code of the template.
        $source = $this->twig->getLoader()
            ->getSourceContext($template);

        $this->annotateBlock($block, $source, $created);
    }

    /**
     * @param _Block|_AnnotatedBlock $block
     *
     * @internal
     */
    public function annotateBlock(array $block, TwigSource $sourceContext, bool $created = false): void
    {
        // Prepare required variables from the given block.
        $template                          = $block['template'];
        $blockName                         = $block['block'];
        [$blockLinesStart, $blockLinesEnd] = $block['block_lines'];

        /** @var string|null $sourceHash */
        $sourceHash                        = $block['source_hash'];
        /** @var string|null $sourceVersion */
        $sourceVersion                     = $block['source_version'];

        if (null === $sourceHash) {
            return;
        }

        // Prepare offset (arrays are zero-indexed, lines are not).
        --$blockLinesStart;
        unset($blockLinesEnd);

        $sourceName      = $sourceContext->getName();
        // Get full source code of the template.
        $sourceCode      = $sourceContext->getCode();
        // Splice in the portion of lines that are needed.
        $sourceCodeLines = \explode("\n", $sourceCode);

        // Add offset, if exists.
        $blockLinesStart += $this->getOffset($sourceName);

        $commentTags = $this->twig->getLexerOptions()['tag_comment'];

        // Define the parameters to use for regex inception.
        $params = [
            // {#
            \preg_quote($commentTags[0], '#'),
            // #}
            \preg_quote($commentTags[1], '#'),
        ];

        $comment   = BlockValidatorExtension::formatComment($sourceHash, $sourceVersion);
        // Move cursor to block's start tag, to match its indentation.
        $blockLine = $sourceCodeLines[$blockLinesStart];

        // Match block's start line, as the previous line might have no indentation.
        $blockLinePattern = \vsprintf('{^\s*}sx', $params);
        if (1 !== \preg_match($blockLinePattern, $blockLine, $blockLineMatch, flags: \PREG_OFFSET_CAPTURE)) {
            throw new SyntaxError(\sprintf('The block-line for block "%s" was not found but expected.', $blockName), $blockLinesStart, $sourceContext);
        }

        // The offset should be 0, as the pattern must start at the beginning of the line (string).
        /** @var _MatchWithOffset $blockLineMatch */
        \assert(0 === $blockLineMatch[0][1]);

        // Add indentation and maintain tags (i.e. `{#`, `#}`), but usually, overwritten blocks are at "col=0" in child templates (if not nested).
        $comment    = $blockLineMatch[0][0] . $commentTags[0] . $comment . $commentTags[1];

        if ($created) {
            // Increment offset.
            $this->addOffset($sourceName);
        }

        \array_splice($sourceCodeLines, $blockLinesStart, (int) ( ! $created), $comment);

        // Reduce to source-code again
        $sourceCode = \implode("\n", $sourceCodeLines);

        $this->writeTemplate($sourceContext, $sourceCode);

        // Make sure to clear caches in the environment.
        $this->twig->removeCache($template);
    }

    /**
     * Write the given source to the current context.
     */
    protected function writeTemplate(TwigSource $source, string $sourceCode): void
    {
        $pathname = $source->getPath();
        $path     = \dirname($pathname);

        if ( ! \file_exists($path)) {
            \mkdir($path, 0755, true);
        }

        // Write back to the template file's path.
        \file_put_contents($pathname, $sourceCode, \LOCK_EX);
    }

    public function reset(): void
    {
        // Reset offsets.
        $this->offsets = [];
    }
}
