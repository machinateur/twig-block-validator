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

namespace Machinateur\Shopware\TwigBlockValidator\Validator;

use Composer\Semver\Semver;
use Machinateur\Shopware\TwigBlockValidator\Command\ConsoleTrait;
use Machinateur\Shopware\TwigBlockValidator\Service\NamespacedPathnameBuilder;
use Machinateur\Shopware\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Machinateur\Shopware\TwigBlockValidator\Twig\Extension\ShopwareBlockVersionExtension;
use Machinateur\Shopware\TwigBlockValidator\Twig\Node\ShopwareBlockCollectionInterface;
use Machinateur\Shopware\TwigBlockValidator\Twig\Node\TwigBlockStackInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

/**
 * @phpstan-import-type _Comment            from ShopwareBlockCollectionInterface
 * @phpstan-import-type _CommentCollection  from ShopwareBlockCollectionInterface
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
class TwigBlockValidator implements ResetInterface
{
    use ConsoleTrait;

    /**
     * @var array<string, _Block>
     */
    private array $blocks = [];

    private readonly NamespacedPathnameBuilder $namespacedPathnameBuilder;

    public function __construct(
        private readonly BlockValidatorEnvironment $twig,
    ) {
        $this->setConsole();

        $this->namespacedPathnameBuilder = new NamespacedPathnameBuilder($this->twig->getLoader());
    }

    /**
     * @param _NamespacedPathMap $scopePaths
     * @param _NamespacedPathMap $templatePaths
     * @param string|null       $version
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

        /** @var _CommentCollection[] $comments */
        $comments = [];
        // Get all comments.
        foreach ($this->loadPaths($scopePaths) as $namespace => $file) {
            $comments[] = $this->twig->getComments(
                $this->namespacedPathnameBuilder->buildNamespacedPathname($namespace, $file)
            );
        }
        /** @var _CommentCollection $comments */
        $comments = \array_merge(...$comments);

        // Run analysis and print result. This looks messed up.
        $this->console?->title('Analysis result');
        $table = $this->console?->createTable();
        $table?->setHeaders(['template', 'parent template', 'block', 'hash', 'version', 'mismatch']);
        // Validate the blocks and prepare result table output.
        /** @var _ValidatedBlockCollection $comments */
        foreach ($comments as & $comment) {
            $this->validateComment($comment, $version);

            if (null === $table) {
                continue;
            }

            $row = [
                ...$comment,
                'block_lines' => \sprintf('%d-%d', ...$comment['block_lines']),
                'hash'    => !$comment['match']['hash']
                    ? \sprintf("<fg=white;bg=red>%s</>\n<fg=black;bg=green>%s</>", $comment['hash'], $comment['source_hash'])
                    : $comment['hash'],
                'version' => !$comment['match']['version']
                    ? \sprintf("<fg=white;bg=red>%s</>\n<fg=black;bg=green>%s</>", $comment['version'], $comment['source_version'])
                    : $comment['version'],
                'valid'   => \sprintf('[%s]', $comment['valid'] ? ' ' : 'x'),
            ];
            unset($row['block_lines'], $row['source_hash'], $row['source_version'], $row['match']);

            $table?->addRow($row);
        }
        $table?->render();

        // Reset the default version.
        $nodeVisitor->setDefaultVersion($defaultVersion);
    }


    /**
     * Validate a single comment block. Shortcut method, internal logic.
     *
     * @param _Comment|_ValidatedComment $comment
     */
    public function validateComment(array & $comment, string $defaultVersion): bool
    {
        $template       = $comment['template'];
        $blockName      = $comment['block'];
        $hash           = $comment['hash'];
        $version        = $comment['version'];

        // Resolve the template block in hierarchy.
        //  TODO: This might need to be adapted, due to special template inheritance logic of `sw_extends` by shopware.
        $parentBlock = $this->resolveParentBlock($template, $blockName);
        // Get source code of the parent block.
        $sourceCode  = $this->getBlockContent($template, $parentBlock);
        $sourceHash  = ShopwareBlockVersionExtension::hash($sourceCode);

        // Enrich the comment, i.e. _ValidatedComment.
        $comment['source_hash']    = $sourceHash;
        $comment['source_version'] = $defaultVersion;
        $comment['valid']          = false;

        $matchHash    = $hash === $sourceHash;
        $matchVersion = Semver::satisfies($version, '~'.$defaultVersion);

        if ($this->output?->isDebug()) {
            $this->console?->warning(\sprintf('Mismatch from block hash to source hash for "%s"!', $template));
            $this->console?->writeln(\sprintf("> <fg=black;bg=green>%s</>", $hash));
            $this->console?->writeln(\sprintf("< <fg=white;bg=red>%s</>", $sourceHash));
            $this->console?->newLine();
        }

        if ($this->output?->isDebug()) {
            $this->console?->warning(\sprintf('Mismatch from block version to source version for "%s"!', $template));
            $this->console?->writeln(\sprintf("> <fg=black;bg=green>%s</>", $version));
            $this->console?->writeln(\sprintf("< <fg=white;bg=red>%s</>", $defaultVersion));
            $this->console?->newLine();
        }

        $comment['match'] = [
            'hash'    => $matchHash,
            'version' => $matchVersion,
        ];

        return $comment['valid'] = ($matchHash && $matchVersion);
    }

    /**
     * Resolve a given template and block name combination to a block struct.
     *
     * @return _Block
     *
     * @throws LoaderError  when the block cannot be resolved
     */
    protected function resolveParentBlock(string $template, string $blockName): array
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
        } while (null === $block);

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
    protected function getBlockContent(string $template, array $block): string
    {
        // Prepare required variables from the given block.
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
            $this->console?->note(\sprintf('Adding namespace "%s" with paths:', $namespace));
            $this->console?->listing($paths);

            foreach ($paths as $path) {
                try {
                    $this->twig->addPath($path, $namespace);
                } catch (LoaderError $error) {
                    $errors[] = $error;
                }
            }
        }

        if (0 < \count($errors)) {
            $this->console?->warning([
                'Twig loader errors!',
            ]);
            $this->console?->listing(\array_map(static fn (LoaderError $error) => $error->getMessage(), $errors));
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
            $this->console?->note(\sprintf('Loading namespace "%s" files:', $namespace));

            $finder = new Finder();
            $finder->in($paths)
                ->files()
                ->name('*.twig');

            /** @var list<LoaderError> $errors */
            $errors = [];

            // Now load all files.
            foreach ($finder as $file) {
                $this->console?->text(\sprintf('* %s', $file->getRelativePathname()));

                try {
                    $this->twig->loadFile($file, $namespace);

                    yield $namespace => $file;
                } catch (LoaderError $error) {
                    $errors[] = $error;

                    throw $error;
                }

            }

            if (0 < \count($errors)) {
                $this->console?->warning([
                    'Twig loader errors!',
                ]);
                $this->console?->listing(\array_map(static fn (LoaderError $error) => $error->getMessage(), $errors));
            }
        }
    }

    public function reset(): void
    {
        $this->setConsole();

        $this->blocks = [];
    }
}
