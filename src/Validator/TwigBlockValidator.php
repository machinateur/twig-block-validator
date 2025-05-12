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
use Machinateur\TwigBlockValidator\Event\Validator\TwigValidateCommentsErrorEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigLoadFilesEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigLoadPathsErrorEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigLoadPathsEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigRegisterPathsErrorEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigRegisterPathsEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigCollectBlocksEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigValidateCommentsEvent;
use Machinateur\TwigBlockValidator\Service\NamespacedPathnameBuilder;
use Machinateur\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Machinateur\TwigBlockValidator\Twig\Extension\BlockVersionExtension;
use Machinateur\TwigBlockValidator\Twig\Node\CommentCollectionInterface;
use Machinateur\TwigBlockValidator\Twig\Node\TwigBlockStackInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
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
 * @phpstan-type _NamespacedPathMap         array<string, array<string>>
 */
class TwigBlockValidator
{
    private readonly NamespacedPathnameBuilder $namespacedPathnameBuilder;

    public function __construct(
        private readonly BlockValidatorEnvironment $twig,
        private readonly EventDispatcherInterface  $dispatcher,
    ) {
        $this->namespacedPathnameBuilder = new NamespacedPathnameBuilder($this->twig->getLoader());
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

        $this->registerPaths($scopePaths);
        $this->registerPaths($templatePaths);

        $nodeVisitor = $this->twig->getBlockNodeVisitor();
        // Get the previous default version to restore it after validation.
        $defaultVersion = $nodeVisitor->getDefaultVersion();
        $nodeVisitor->setDefaultVersion($version);

        /** @var list<TwigError> $errors */
        $errors   = [];
        $comments = $this->loadComments($scopePaths, $errors);

        if (0 < \count($comments)) {
            $this->dispatcher->dispatch(
                $event = new TwigValidateCommentsEvent($comments, $version)
            );

            $event->notify(TwigValidateCommentsEvent::CALL_BEGIN);

            /** @var _ValidatedBlockCollection $comments */
            foreach ($comments as & $comment) {
                try {
                    $this->validateComment($comment, $version);

                    $event->notify(TwigValidateCommentsEvent::CALL_STEP, $comment);
                } catch (TwigError $error) {
                    $errors[] = $error;

                    continue;
                }

            }

            $event->notify(TwigValidateCommentsEvent::CALL_END);
        }

        if (0 < \count($errors)) {
            $this->dispatcher->dispatch(
                new TwigValidateCommentsErrorEvent($errors)
            );
        }

        // Reset the default version.
        $nodeVisitor->setDefaultVersion($defaultVersion);
    }

    /**
     * Load the given paths, optionally save all errors to the provided array.
     *  Collect all blocks and comments from these templates.
     *
     * @param _NamespacedPathMap $scopePaths
     * @param list<TwigError>    $errors
     *
     * @return _CommentCollection
     */
    protected function loadComments(array $scopePaths, array & $errors = []): array
    {
        $blocks    = [];
        $comments  = [];
        $templates = [];

        // Get all comments and blocks.
        foreach ($this->loadPaths($scopePaths) as $namespace => $file) {
            /** @var SplFileInfo $file */
            $templates[] = $template = $this->namespacedPathnameBuilder->buildNamespacedPathname($namespace, $file);

            try {
                $blocks[]   = $this->twig->getBlocks($template);
                $comments[] = $this->twig->getComments($template);
            } catch (TwigError $error) {
                $errors[]   = $error;
            }
        }

        /** @var _Block[]           $blocks */
        $blocks   = \array_merge(...$blocks);
        /** @var _CommentCollection $comments */
        $comments = \array_merge(...$comments);

        $this->dispatcher->dispatch(
            new TwigCollectBlocksEvent($templates, $blocks)
        );

        return $comments;
    }

    /**
     * Validate a single comment block. Shortcut method, internal logic.
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
        $parentBlock = $this->resolveParentBlock($template, $blockName);
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
     * Resolve a given template and block name combination to a block struct.
     *
     * @return _Block|null
     *
     * @throws LoaderError      when the block cannot be resolved or the template does not exist
     * @throws RuntimeError     when the generated code is erroneous
     * @throws SyntaxError      when there is a syntax error, like missing tags
     */
    protected function resolveParentBlock(string $template, string $blockName): ?array
    {
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

    /**
     * Add the given paths for the given namespaces, without resetting the namespace paths of the loader.
     *
     * @param _NamespacedPathMap $scopePaths
     */
    protected function registerPaths(array $scopePaths): void
    {
        /** @var list<LoaderError> $errors */
        $errors = [];

        // Register all paths with the loader.
        foreach ($scopePaths as $namespace => $paths) {
            $this->dispatcher->dispatch(
                new TwigRegisterPathsEvent($namespace, $paths)
            );

            foreach ($paths as $path) {
                try {
                    $this->twig->addPath($path, $namespace);
                } catch (LoaderError $error) {
                    $key          = $this->namespacedPathnameBuilder->buildNamespacedPathname($namespace, $path);
                    $errors[$key] = $error;
                }
            }
        }

        if (0 < \count($errors)) {
            $this->dispatcher->dispatch(
                new TwigRegisterPathsErrorEvent($errors)
            );
        }
    }

    /**
     * Load the given paths for the given namespaces.
     *
     * @param _NamespacedPathMap $scopePaths
     *
     * @return \Generator<string, SplFileInfo>
     */
    public function loadPaths(array $scopePaths): \Generator
    {
        foreach ($scopePaths as $namespace => $paths) {
            $this->dispatcher->dispatch(
                new TwigLoadPathsEvent($namespace, $paths)
            );

            $finder = new Finder();
            $finder->in($paths)
                ->files()
                ->name('*.twig');

            $this->dispatcher->dispatch(
                $event = new TwigLoadFilesEvent($namespace, $paths, $finder)
            );

            /** @var list<LoaderError> $errors */
            $errors = [];

            $event->notify(TwigLoadFilesEvent::CALL_BEGIN);

            // Now load all files.
            foreach ($finder->getIterator() as $file) {
                try {
                    $this->twig->loadFile($file, $namespace);

                    $event->notify(TwigLoadFilesEvent::CALL_STEP, $file);
                } catch (LoaderError $error) {
                    $errors[] = $error;

                    continue;
                }

                yield $namespace => $file;
            }

            $event->notify(TwigLoadFilesEvent::CALL_END);

            if (0 < \count($errors)) {
                $this->dispatcher->dispatch(
                    new TwigLoadPathsErrorEvent($errors)
                );
            }
        }
    }
}
