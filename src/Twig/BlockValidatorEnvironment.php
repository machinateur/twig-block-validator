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

namespace Machinateur\TwigBlockValidator\Twig;

use Machinateur\TwigBlockValidator\Service\NamespacedPathnameBuilder;
use Machinateur\TwigBlockValidator\Twig\Extension\BlockVersionExtension;
use Machinateur\TwigBlockValidator\Twig\Node\CommentCollectionInterface;
use Machinateur\TwigBlockValidator\Twig\Node\TwigBlockStackInterface;
use Machinateur\TwigBlockValidator\Twig\NodeVisitor\BlockNodeVisitor;
use Machinateur\Twig\Extension\CommentExtension;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface as CacheItemInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Lexer;
use Twig\Loader\FilesystemLoader;
use Twig\TemplateWrapper;

/**
 * @phpstan-import-type _CommentCollection  from CommentCollectionInterface
 * @phpstan-import-type _Block              from TwigBlockStackInterface
 */
class BlockValidatorEnvironment extends Environment implements ResetInterface
{
    private BlockNodeVisitor $nodeVisitor;

    private NamespacedPathnameBuilder $namespacedPathnameBuilder;

    public function __construct(
        Environment                     $platformTwig,
        private readonly CacheInterface $blockCache,
        private readonly CacheInterface $commentCache,
        ?string                         $version = null,
    ) {
        $loader = new FilesystemLoader();

        parent::__construct($loader, [
            'debug' => $platformTwig->isDebug(),
        ]);

        // Add the lexer extension, needed for comment access and processing.
        CommentExtension::setLexer($this);
        $this->addExtension(new CommentExtension());

        // Add the parser extension, needed for block tracking.
        BlockVersionExtension::setParser($this);
        // Use node visitor directly, instead of using the extension class, to extract the block collection.
        $this->addNodeVisitor(
            $this->nodeVisitor = new BlockNodeVisitor(defaultVersion: $version)
        );

        // No cache, because that would hinder lexing on load, and thus needed to properly collect comments.
        $this->setCache(false);

        // https://github.com/shopware/shopware/blob/6.6.x/src/Core/Framework/Adapter/Twig/StringTemplateRenderer.php
        foreach ($platformTwig->getExtensions() as $extension) {
            if ($this->hasExtension($extension::class)) {
                continue;
            }
            $this->addExtension($extension);
        }
        if ($this->hasExtension(CoreExtension::class) && $platformTwig->hasExtension(CoreExtension::class)) {
            /** @var CoreExtension $coreExtensionInternal */
            $coreExtensionInternal = $this->getExtension(CoreExtension::class);
            /** @var CoreExtension $coreExtensionGlobal */
            $coreExtensionGlobal = $platformTwig->getExtension(CoreExtension::class);

            $coreExtensionInternal->setTimezone($coreExtensionGlobal->getTimezone());
            $coreExtensionInternal->setDateFormat(...$coreExtensionGlobal->getDateFormat());
            $coreExtensionInternal->setNumberFormat(...$coreExtensionGlobal->getNumberFormat());
        }

        $this->namespacedPathnameBuilder = new NamespacedPathnameBuilder($loader);
    }

    public function render($name, array $context = []): string
    {
        throw new \LogicException('The validator environment does not support rendering!');
    }

    public function getLoader(): FilesystemLoader
    {
        $loader = parent::getLoader();
        \assert($loader instanceof FilesystemLoader);
        return $loader;
    }

    /**
     * Add the given path for the given namespace, without resetting the namespace paths of the loader.
     *
     * @throws LoaderError  when the given path does not exist as a directory
     */
    public function addPath(string $path, string $namespace = FilesystemLoader::MAIN_NAMESPACE): void
    {
        //if ( ! \is_dir($path)) {
        //    return;
        //}

        try {
            $this->getLoader()
                ->addPath($path, $namespace);
        } catch (LoaderError $error) {
            // The error will cause all following paths to be skipped for the namespace!
            throw new LoaderError(\sprintf('The "%s" directory does not exist for namespace "%s"!', $path, $namespace), previous: $error);
        }
    }

    /**
     * @throws LoaderError  when the template cannot be loaded
     */
    public function loadFile(SplFileInfo $file, string $namespace = FilesystemLoader::MAIN_NAMESPACE): TemplateWrapper
    {
        $name = $this->namespacedPathnameBuilder->buildNamespacedPathname($namespace, $file);

        try {
            return $this->load($name);
        } catch (TwigError $error) {
            throw new LoaderError(\sprintf('Failed to load file "%s" (namespace "%s").', $file->getRelativePathname(), $namespace), previous: $error);
        }
    }

    public function load($name): TemplateWrapper
    {
        if ( ! \is_string($name)) {
            throw new \InvalidArgumentException('The name must be a string.');
        }

        if (isset($this->templateCache[$name])) {
            return $this->templateCache[$name];
        }

        $template   = parent::load($name);
        $collection = $this->nodeVisitor->resetCollection();
        // Will not be called when already in cache.
        $this->blockCache->get($this->getCacheKey($name, 'blocks'),
                static function (CacheItemInterface $item) use ($collection): array {
                $item->expiresAfter(new \DateInterval('PT10M'));

                $blocks = $collection->getBlocks();

                // Build a map by name.
                return \array_combine(\array_column($blocks, 'block'), $blocks);
            }
        );
        // Will not be called when already in cache.
        $this->commentCache->get($this->getCacheKey($name, 'comments'),
            static function (CacheItemInterface $item) use ($collection): array {
                $item->expiresAfter(new \DateInterval('PT10M'));

                return $collection->getComments();
            }
        );

        return $template;
    }

    /**
     * Generate a cache key.
     *
     * @throws LoaderError  when the template does not exist
     */
    public function getCacheKey(string $name, string $prefix): string
    {
        $name  = $prefix.':'.$this->getLoader()
            ->getCacheKey($name);
        $chars = CacheItemInterface::RESERVED_CHARACTERS;

        if (false !== strpbrk($name, $chars)) {
            $name = \preg_replace(\sprintf('{[%s]}sx', \preg_quote($chars)), '_', $name);
        }

        return $name;
    }

    /**
     *
     * This method **must** be called after {@see BlockValidatorEnvironment::load()} for any given template.
     *
     * ```
     * $twig->load($template);
     * $blocks = $twig->getBlocks($template);
     * ```
     *
     * @return array<string, _Block>
     *
     * @throws RuntimeError when the blocks of the template are not cached
     * @throws LoaderError  when the template does not exist
     */
    public function getBlocks(string $name): array
    {
        /** @var CacheItemInterface $item */
        $item = $this->blockCache->getItem($this->getCacheKey($name, 'blocks'));

        if ( ! $item->isHit()) {
            throw new RuntimeError(\sprintf('The template "%s" is not in block cache!', $name));
        }

        return $item->get();
    }

    /**
     * Get all comments from the cache.
     *
     * This method **must** be called after {@see BlockValidatorEnvironment::load()} for any given template.
     *
     * ```
     * $twig->load($template);
     * $comments = $twig->getComments($template);
     * ```
     *
     * @throws RuntimeError when the comments of the template are not cached
     * @throws LoaderError  when the template does not exist
     *
     * @return _CommentCollection
     */
    public function getComments(string $name): array
    {
        /** @var CacheItemInterface $item */
        $item = $this->commentCache->getItem($this->getCacheKey($name, 'comments'));

        if ( ! $item->isHit()) {
            throw new RuntimeError(\sprintf('The template "%s" is not in comment cache!', $name));
        }

        return $item->get();
    }

    /**
     * @internal
     */
    public function getBlockNodeVisitor(): BlockNodeVisitor
    {
        return $this->nodeVisitor;
    }

    /**
     * @internal
     */
    public function getLexerOptions(): array
    {
        static $getLexer;
        $getLexer ??= \Closure::bind(function (Environment $twig): Lexer {
            return $twig->lexer;
        }, null, Environment::class);

        static $getOptions;
        $getOptions ??= \Closure::bind(function (Environment $twig) use ($getLexer): array {
            return $getLexer($twig)->options;
        }, null, Lexer::class);

        return $getOptions($this);
    }

    public function reset(): void
    {
        $this->resetGlobals();

        $this->getLoader()
            ->setPaths([]);
        $this->setCache(false);

        $this->nodeVisitor->resetCollection();
    }
}
