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

use Machinateur\TwigBlockValidator\TwigBlockValidatorOutput;
use Machinateur\TwigBlockValidator\Validator\TwigBlockValidator;
use Twig\Loader\FilesystemLoader;

/**
 * @phpstan-import-type _NamespacedPathMap  from TwigBlockValidator
 *
 * @property TwigBlockValidatorOutput $output
 */
trait ConsoleCommandTrait
{
    /**
     * Prepare a namespaced path map from a list of paths.
     *
     * Format:
     *
     * ```
     * @Storefront:vendor/shopware/storefront/Resources/views
     * @Administration:vendor/shopware/administration/Resources/views
     * ...
     * ```
     *
     * @param array<string> $templatePaths
     * @return _NamespacedPathMap
     */
    protected function resolveNamespaces(array $templatePaths): array
    {
        $console = $this->output->getConsole();

        $paths   = [];
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
                if ($console?->isVeryVerbose()) {
                    $console?->block('The directory "%s" was not found. Skipping.', 'WARNING', 'fg=black;bg=yellow');
                }

                continue;
            }

            $paths[$namespace][] = $path;
        }

        return $paths;
    }
}
