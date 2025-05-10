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

namespace Machinateur\Shopware\TwigBlockValidator\Command;

use Composer\InstalledVersions;
use Machinateur\Shopware\TwigBlockValidator\Validator\TwigBlockValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Loader\FilesystemLoader;

/**
 * @phpstan-import-type _NamespacedPathMap  from TwigBlockValidator
 */
class ShopwareTwigBlockValidateCommand extends Command
{
    use ConsoleTrait;

    public const DEFAULT_NAME = 'shopware:twig-block:validate';

    public function __construct(
        private readonly TwigBlockValidator $validator,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    public static function getDefaultName(): ?string
    {
        return self::DEFAULT_NAME;
    }

    protected function configure(): void
    {
        $this
            ->addOption('template-path', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Twig template path to load')
            ->addOption('validate-path', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Twig template path to validate')
            ->addOption('default-version', 'r', InputOption::VALUE_OPTIONAL, 'The version number required')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output, ?OutputStyle $console = null): int
    {
        $console     ??= new SymfonyStyle($input, $output);
        $this->setConsole($console);

        $templatePaths = (array)$input->getOption('template-path');
        $validatePaths = (array)$input->getOption('validate-path');
        $version       = $input->getOption('default-version');

        try {
            $version ??= InstalledVersions::getVersion('shopware/storefront');
        } catch (\OutOfBoundsException) {
            // Failed to get the package, as it is not installed.
        }

        $templatePaths = $this->resolveNamespaces($templatePaths);
        $validatePaths = $this->resolveNamespaces($validatePaths);

        $this->validator->setConsole($console);
        $this->validator->validate($validatePaths, $templatePaths, $version);
        $this->validator->reset();

        return Command::SUCCESS;
    }

    /**
     * @param array<string> $templatePaths
     * @return _NamespacedPathMap
     */
    private function resolveNamespaces(array $templatePaths): array
    {
        $paths = [];
        foreach ($templatePaths as $path) {
            if (\str_contains($path, ':')) {
                if ($path[0] === '@') {
                    $path = \substr($path, 1);
                }

                [$namespace, $path] = \explode(':', $path);
            } else {
                $namespace = FilesystemLoader::MAIN_NAMESPACE;
            }

            // Ignore non-existent paths for now.
            if ( ! \is_dir($path)) {
                if ($this->output?->isVerbose()) {
                    $this->console?->block('The directory "%s" was not found. Skipping.', 'WARNING', 'fg=black;bg=yellow');
                }

                continue;
            }

            $paths[$namespace][] = $path;
        }

        return $paths;
    }
}
