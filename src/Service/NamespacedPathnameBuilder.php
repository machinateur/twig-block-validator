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

namespace Machinateur\Shopware\TwigBlockValidator\Service;

use Symfony\Component\Finder\SplFileInfo;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;

readonly class NamespacedPathnameBuilder
{
    /**
     * @var \Closure(FilesystemLoader, string, string): array{0:string,1:string}
     *
     * @see FilesystemLoader::parseName()
     */
    private \Closure $parseName;

    public function __construct(
        private FilesystemLoader $loader,
    ) {
        $this->parseName = \Closure::bind(static function (FilesystemLoader $loader, string $name, string $default = FilesystemLoader::MAIN_NAMESPACE): array {
            return $loader->parseName($name, $default);
        }, null, FilesystemLoader::class);
    }

    public function buildNamespacedPathname(string $namespace, SplFileInfo $file): string
    {
        return '@' . $namespace . '/' . $file->getRelativePathname();
    }

    /**
     * @return array{0:string,1:string}
     *
     * @throws \InvalidArgumentException    when the given pathname cannot be parsed
     *
     * @see FilesystemLoader::parseName()
     */
    public function parseNamespacedPathname(string $pathname, string $default = FilesystemLoader::MAIN_NAMESPACE): array
    {
        try {
            return ($this->parseName)($this->loader, $pathname, $default);
        } catch (LoaderError $error) {
            throw new \InvalidArgumentException(\sprintf('Failed to parse filepath "%s" (namespace "%s").', $pathname, $default), previous: $error);
        }
    }
}
