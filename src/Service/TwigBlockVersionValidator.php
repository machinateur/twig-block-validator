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

namespace Machinateur\Shopware\TwigBlockVersion\Service;

use Composer\Semver\Semver;
use Machinateur\Shopware\TwigBlockVersion\Twig\Extension\ShopwareBlockVersionExtension;
use Machinateur\Shopware\TwigBlockVersion\Twig\Node\ShopwareBlockCollectionInterface;
use Machinateur\Shopware\TwigBlockVersion\Twig\Node\TwigBlockStackInterface;
use Machinateur\Shopware\TwigBlockVersion\Twig\NodeVisitor\BlockNodeVisitor;
use Machinateur\Twig\Extension\CommentExtension;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\CoreExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TemplateWrapper;

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
 * @phpstan-type _FileCache                 array<string, SplFileInfo>
 * @phpstan-type _TemplateCache             array<string, TemplateWrapper>
 * @phpstan-type _BlockCache                array<string, array<string, _Block>>
 */
class TwigBlockVersionValidator
{
    private readonly Environment $twig;

    private readonly BlockNodeVisitor $nodeVisitor;

    /**
     * @var _FileCache
     */
    private array $fileCache     = [];

    /**
     * @var _TemplateCache
     */
    private array $templateCache = [];

    /**
     * @var _BlockCache
     */
    private array $blockCache    = [];

    /**
     * @var \Closure(FilesystemLoader, string, string): array{0:string,1:string}
     *
     * @see FilesystemLoader::parseName()
     */
    private readonly \Closure $parseName;

    private ?OutputStyle $console = null;

    public function __construct(?Environment $platformTwig = null, ?string $version = null)
    {
        $this->twig = new Environment(
            $loader = new FilesystemLoader(),
        );

        // Add the lexer extension, needed for comment access and processing.
        CommentExtension::setLexer($this->twig);
        $this->twig->addExtension(new CommentExtension());

        // Add the parser extension, needed for block tracking.
        ShopwareBlockVersionExtension::setParser($this->twig);
        // Use node visitor directly, instead of using the extension class, to extract the block collection.
        $this->twig->addNodeVisitor(
            $this->nodeVisitor = new BlockNodeVisitor(defaultVersion: $version)
        );

        $this->parseName = \Closure::bind(static function (FilesystemLoader $loader, string $name, string $namespace = FilesystemLoader::MAIN_NAMESPACE): array {
            return $loader->parseName($name, $namespace);
        }, null, $loader);

        if (null !== $platformTwig) {
            foreach ($platformTwig->getExtensions() as $extension) {
                if ($this->twig->hasExtension($extension::class)) {
                    continue;
                }
                $this->twig->addExtension($extension);
            }
            if ($this->twig->hasExtension(CoreExtension::class) && $platformTwig->hasExtension(CoreExtension::class)) {
                /** @var CoreExtension $coreExtensionInternal */
                $coreExtensionInternal = $this->twig->getExtension(CoreExtension::class);
                /** @var CoreExtension $coreExtensionGlobal */
                $coreExtensionGlobal = $platformTwig->getExtension(CoreExtension::class);

                $coreExtensionInternal->setTimezone($coreExtensionGlobal->getTimezone());
                $coreExtensionInternal->setDateFormat(...$coreExtensionGlobal->getDateFormat());
                $coreExtensionInternal->setNumberFormat(...$coreExtensionGlobal->getNumberFormat());
            }
        }
    }

    /**
     * Add the given paths for the given namespace. Shortcut method, internal logic.
     */
    private function addPaths(array $paths, string $namespace = FilesystemLoader::MAIN_NAMESPACE): void
    {
        foreach ($paths as $path) {
            if ( ! \is_dir($path)) {

                continue;
            }

            try {
                $this->getLoader()
                    ->addPath($path, $namespace);
            } catch (LoaderError $e) {
                $this->console?->warning([
                    'Twig loader error!',
                    \sprintf('Directory "%s" does not exist for namespace "%s"!', $path, $namespace),
                    $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Load the given paths for the given namespace. Shortcut method, internal logic.
     */
    private function loadPaths(array $paths, string $namespace = FilesystemLoader::MAIN_NAMESPACE): void
    {
        $finder = new Finder();
        $finder->in($paths)
            ->files()
            ->name('*.twig');

        // Now load all files.
        foreach ($finder as $file) {
            $this->console?->text(\sprintf('* %s', $file->getRelativePathname()));

            $pathname = $this->buildNamespacedPathname($namespace, $file);
            try {
                $this->fileCache    [$pathname] = $file;
                $this->templateCache[$pathname] = $this->twig->load($pathname);
            } catch (LoaderError $e) {
                $this->console?->warning([
                    'Twig loader error!',
                    \sprintf('Failed to load file "%s" (namespace "%s").', $pathname, $namespace),
                    $e->getMessage(),
                ]);
            } catch (RuntimeError $e) {
                $this->console?->warning([
                    'Twig runtime error!',
                    \sprintf('Failed to load file "%s" (namespace "%s").', $pathname, $namespace),
                    $e->getMessage(),
                ]);
            } catch (SyntaxError $e) {
                $this->console?->warning([
                    'Twig syntax error!',
                    \sprintf('Failed to load file "%s" (namespace "%s").', $pathname, $namespace),
                    $e->getMessage(),
                ]);
            }

            //if (isset($e)) {
            //    throw new \UnexpectedValueException('Twig error', previous: $e);
            //}
        }
    }

    private function buildNamespacedPathname(string $namespace, SplFileInfo $file): string
    {
        return '@' . $namespace . '/' . $file->getRelativePathname();
    }

    /**
     * @return array{0:string,1:string}
     *
     * @throws \InvalidArgumentException    when the given pathname cannot be parsed.
     *@see FilesystemLoader::parseName()
     *
     * @see TwigBlockVersionValidator::$parseName
     */
    private function parseNamespacedPathname(string $pathname, string $namespace = FilesystemLoader::MAIN_NAMESPACE): array
    {
        try {
            return ($this->parseName)($this->getLoader(), $pathname, $namespace);
        } catch (LoaderError $e) {
            $this->console?->warning([
                'Twig loader error!',
                \sprintf('Failed to parse filepath "%s" (namespace "%s").', $pathname, $namespace),
                $e->getMessage(),
            ]);

            throw new \InvalidArgumentException('Invalid name', previous: $e);
        }
    }

    /**
     * Validate the given paths with the given template context available, for the given default version.
     *
     * @return _ValidatedBlockCollection
     */
    public function validate(array $templatePaths, array $validatePaths, string $version): array
    {
        // First register all paths with the loader.
        foreach ([$templatePaths, $validatePaths] as $scopePaths) {
            foreach ($scopePaths as $namespace => $paths) {
                $this->console?->note(\sprintf('Adding namespace "%s" with paths:', $namespace));
                $this->console?->listing($paths);

                $this->addPaths($paths, $namespace);
            }
        }

        $defaultVersion = $this->nodeVisitor->getDefaultVersion();
        $this->nodeVisitor->setDefaultVersion($version);

        // Utilize fall-through from before to determine which paths need validation.
        foreach ($scopePaths as $namespace => $paths) {
            $this->console?->note(\sprintf('Loading namespace "%s" files:', $namespace));

            $this->loadPaths($paths, $namespace);
        }

        $this->updateBlockCache(
            $collection = $this->getBlockCollection(true)
        );

        /** @var _CommentCollection $comments */
        $comments = $collection->getComments();

        $this->console?->title('Analysis result');

        if ($this->console instanceof SymfonyStyle) {
            $table = $this->console?->createTable();
        } elseif (null !== $this->console) {
            $refl   = new \ReflectionProperty(OutputStyle::class, 'output');
            //$refl->setAccessible(true);
            $output = $refl->getValue($this->console);
            \assert($output instanceof OutputInterface);
            $table  = new Table($output);
        } else {
            $table = null;
        }

        // Validate the blocks and prepare result table output...
        /** @var _ValidatedBlockCollection $comments */
        $table?->setHeaders(['template', 'parent template', 'block', 'hash', 'version', 'mismatch']);
        foreach ($comments as & $comment) {
            $this->validateBlock($comment, $version);

            $row = [
                ...$comment,
                'block_lines' => \sprintf('%d-%d', ...$comment['block_lines']),
                'hash'        => ! $comment['match']['hash']
                    ? \sprintf("<fg=white;bg=red>%s</>\n<fg=black;bg=green>%s</>", $comment['hash'], $comment['source_hash'])
                    : $comment['hash'],
                'version'     => ! $comment['match']['version']
                    ? \sprintf("<fg=white;bg=red>%s</>\n<fg=black;bg=green>%s</>", $comment['version'], $comment['source_version'])
                    : $comment['version'],
                'valid'       => \sprintf('[%s]', $comment['valid'] ? ' ' : 'x'),
            ];
            unset($row['block_lines'], $row['source_hash'], $row['source_version'], $row['match']);
            $table?->addRow($row);
        }
        $table?->render();

        // Reset the default version.
        $this->nodeVisitor->setDefaultVersion($defaultVersion);

        return $comments;
    }

    /**
     * Validate a single comment block. Shortcut method, internal logic.
     *
     * @param _Comment|_ValidatedComment $data
     */
    private function validateBlock(array & $data, string $defaultVersion): bool
    {
        $pathname       = $data['template'];
        $parentPathname = $data['parent_template'];
        $blockName      = $data['block'];
        $hash           = $data['hash'];
        $version        = $data['version'];

        if ( ! isset($this->templateCache[$parentPathname])) {
            $this->templateCache[$parentPathname] = $this->twig->load($parentPathname);
            //$this->files    [$parentPathname] = new SplFileInfo();

            $this->updateBlockCache(
                $this->getBlockCollection(true)
            );
        }

        // Take it by name from cache, since it was just updated or pre-filled during loading.
        $parentBlocks = $this->blockCache[$parentPathname];

        // TODO: Error handling, as it could be inherited from further up the chain. Put this into a loop.
        $blockLines = $parentBlocks[$blockName]['block_lines'];

        //$sourceContext   = $this->templates[$parentPathname]->getSourceContext();
        $sourceContext   = $this->getLoader()
            ->getSourceContext($parentPathname);
        $sourceCode      = $sourceContext->getCode();
        $sourceCodeLines = \explode("\n", $sourceCode);
        $sourceCode      = \implode("\n", \array_slice($sourceCodeLines, $blockLines[0] - 1, $blockLines[1] - $blockLines[0]));

        $output = null;
        if (null !== $this->console) {
            // TODO: Move to utils.
            $refl = new \ReflectionProperty(OutputStyle::class, 'output');
            //$refl->setAccessible(true);
            $output = $refl->getValue($this->console);
            \assert($output instanceof OutputInterface);
        }

        $sourceHash = ShopwareBlockVersionExtension::hash($sourceCode);

        $data['source_hash']    = $sourceHash;
        $data['source_version'] = $defaultVersion;
        $data['valid']          = false;

        //if (1 === \preg_match('{\s*\{%\s*block\s+\w\s+%\}(.*)}sx')) {
        //    // TODO: Parse out content from block.
        //         For that to work, I need to find out where exactly the blocks start/end here, as parsing will be to fuzzy/unreliable with regexes and nested blocks.
        //}

        // TODO: Refactor this method. It is not nice to look at, and it does too many things.
        $matchHash    = $hash === $sourceHash;
        $matchVersion = Semver::satisfies($version, '~'.$defaultVersion);

        if ($output?->isDebug()) {
            $this->console?->warning(\sprintf('Mismatch from block hash to source hash for "%s"!', $pathname));
            $this->console?->writeln(\sprintf("> <fg=black;bg=green>%s</>", $hash));
            $this->console?->writeln(\sprintf("< <fg=white;bg=red>%s</>", $sourceHash));
            $this->console?->newLine();
        }

        if ($output?->isDebug()) {
            $this->console?->warning(\sprintf('Mismatch from block version to source version for "%s"!', $pathname));
            $this->console?->writeln(\sprintf("> <fg=black;bg=green>%s</>", $version));
            $this->console?->writeln(\sprintf("< <fg=white;bg=red>%s</>", $defaultVersion));
            $this->console?->newLine();
        }

        $data['match'] = [
            'hash'    => $matchHash,
            'version' => $matchVersion,
        ];

        return $data['valid'] = ($matchHash && $matchVersion);
    }

    /**
     * Shortcut to get the environment's loader (`FilesystemLoader`).
     */
    private function getLoader(): FilesystemLoader
    {
        $loader = $this->twig->getLoader();
        \assert($loader instanceof FilesystemLoader);
        return $loader;
    }

    /**
     * Get the currently collected blocks and optional reset the collection afterward.
     */
    private function getBlockCollection(bool $reset = false): ShopwareBlockCollectionInterface
    {
        $collection =  $this->nodeVisitor->getCollection();
        if ($reset) {
            $this->nodeVisitor->resetCollection();
        }
        return $collection;
    }

    /**
     * Reset any cached/properties used for processing.
     */
    public function reset(): void
    {
        $this->fileCache     = [];
        $this->templateCache = [];
        $this->blockCache    = [];

        $this->getLoader()
            ->setPaths([]);
        $this->twig->setCache(false);

        // Discard result.
        $this->getBlockCollection(true);

        $this->setConsole(null);
    }

    public function setConsole(?OutputStyle $console): void
    {
        $this->console = $console;
    }

    private function updateBlockCache(ShopwareBlockCollectionInterface $collection): void
    {
        foreach ($collection->getBlocks() as $block) {
            $this->blockCache[$block['template']][$block['block']] = $block;
        }
    }
}
