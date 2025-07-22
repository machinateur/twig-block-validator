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

use Machinateur\TwigBlockValidator\Box\BoxKernel;
use Machinateur\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Machinateur\TwigBlockValidator\TwigBlockValidatorOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * @phpstan-import-type _NamespacedPathMap  from BlockValidatorEnvironment
 */
abstract class AbstractConsoleCommand extends Command
{
    use ConsoleCommandTrait;

    /**
     * @var string
     */
    public const DEFAULT_NAME = null;

    private ?string $version = null;

    /**
     * @param string|null $name     The override command name. This is useful for adding it as composer script.
     */
    public function __construct(
        protected readonly TwigBlockValidatorOutput $output,
        protected readonly Environment              $platformTwig,
        ?string                                     $name = null,
    ) {
        parent::__construct($name);
    }

    public static function getDefaultName(): ?string
    {
        return static::DEFAULT_NAME ?? parent::getDefaultName();
    }

    protected function configure(): void
    {
        $this
            ->addOption('template', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Twig template path to load')
            ->addOption('validate', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Twig template path to validate')
            ->addOption('use-version', 'r', InputOption::VALUE_OPTIONAL, 'The version number to require', false)
        ;
    }

    /**
     * Copy filesystem loader paths from platform twig (only if supported and not running as phar).
     *
     * @return _NamespacedPathMap
     */
    protected function getPlatformTemplatePaths(): array
    {
        if (BoxKernel::isPhar()) {
            return [];
        }

        $platformPaths  = [];
        $platformLoader = $this->platformTwig->getLoader();

        if ($platformLoader instanceof ChainLoader) {
            // Find the filesystem loader, if chained (default).
            foreach ($platformLoader->getLoaders() as $platformLoader) {
                if ($platformLoader instanceof FilesystemLoader) {
                    break;
                }

                // Pass if no filesystem loader is found. This relies on fall-through of the loop scope.
            }
        }

        if ($platformLoader instanceof FilesystemLoader) {
            foreach ($platformLoader->getNamespaces() as $namespace) {
                if ('!' === $namespace[0]) {
                    continue;
                }

                $platformPaths[$namespace] = $platformLoader->getPaths($namespace);
            }
        }

        return $platformPaths;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }
}
