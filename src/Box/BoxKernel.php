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

namespace Machinateur\TwigBlockValidator\Box;

use Machinateur\TwigBlockValidator\TwigBlockValidatorKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

class BoxKernel extends TwigBlockValidatorKernel
{
    protected readonly string           $workdir;
    protected static   ?KernelInterface $kernel = null;

    public function __construct(string $environment = 'prod', bool $debug = false, ?string $workdir = null)
    {
        parent::__construct($environment, $debug);

        if (null !== $workdir && ! \is_dir($workdir)) {
            throw new \InvalidArgumentException(\sprintf('The workdir "%s" does not exist', $workdir));
        }

        $this->workdir = $workdir ?? \getcwd();

        // Initialize the kernel directly, as it is needed for the `build()` step of our own container.
        $this->getKernel();
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/../..';
    }

    public function getCacheDir(): string
    {
        return $this->workdir.'/.twig-block-validator';
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }

    /**
     * @see \Symfony\Component\HttpKernel\Kernel::getContainerClass()
     */
    protected function getContainerClass(): string
    {
        // Make sure the parent class is used here, since otherwise the cache cannot be warmed properly before `phar` compilation.
        $class = parent::class;
        $class = \str_contains($class, "@anonymous\0") ? \get_parent_class($class).\str_replace('.', '_', ContainerBuilder::hash($class)) : $class;
        $class = \str_replace('\\', '_', $class).\ucfirst($this->environment).($this->debug ? 'Debug' : '').'Container';

        if (!\preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class)) {
            throw new \InvalidArgumentException(\sprintf('The environment "%s" contains invalid characters, it can only contain characters allowed in PHP class names.', $this->environment));
        }

        return $class;
    }

    /**
     * Get or create a kernel from the current workdir.
     *  The returned kernel instance is already booted.
     */
    public function getKernel(): ?KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }

        // Resolve and boot the application kernel.
        $kernel = $this->createKernel($this->workdir, [
            'environment' => 'test',
            'debug'       => $this->debug,
        ]);
        $kernel?->boot();

        return static::$kernel = $kernel;
    }

    public function boot(): void
    {
        $this->getKernel();

        parent::boot();
    }

    public function reboot(?string $warmupDir): void
    {
        parent::reboot($warmupDir);

        if ( ! static::$kernel) {
            return;
        }

        static::$kernel->reboot($warmupDir);
    }

    public function shutdown(): void
    {
        parent::shutdown();

        if ( ! static::$kernel) {
            return;
        }

        // Shut down the application kernel.
        static::$kernel->shutdown();
        static::$kernel = null;
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if ( ! static::$kernel) {
            return;
        }

        try {
            $container->setParameter('twig.default_path',
                static::$kernel->getContainer()
                    ->getParameter('twig.default_path')
            );
        } catch (\Throwable $e) {
            throw new \UnexpectedValueException('Failed to set platform default twig path. ' . $e->getMessage(), previous: $e);
        }

        try {
            // Copy over twig service from application kernel.
            $container->set('twig',
                // Get twig, even though it's private, by leveraging the built-in test container.
                static::$kernel->getContainer()
                    ->get('test.service_container')
                    ->get('twig')
            );
        } catch (\Throwable $e) {
            throw new \UnexpectedValueException('Failed to copy platform twig. ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Check whether the application is currently running as `phar` archive.
     *
     * @see https://github.com/box-project/box/blob/main/doc/faq.md#detecting-that-you-are-inside-a-phar
     */
    final public static function isPhar(): bool
    {
        return '' !== \Phar::running(false);
    }

    /**
     * When running inside the phar file, determine this for the application kernel,
     *  instead of checking internal versions from `\Composer\InstalledVersions`, which stays un-prefixed.
     */
    public static function getShopwareVersion(): ?string
    {
        if (self::isPhar()) {
            // TODO: Resolve static vs instance conflict, it would make sense to set the kernel globally,
            //  since this class will only be instantiated once during lifecycle. Also, handle the "param not found" error.
            return static::$kernel?->getContainer()
                ->getParameter('kernel.shopware_version');
        }

        return parent::getShopwareVersion();
    }

    /**
     * Resolve the `KERNEL_CLASS` env-var for the given workdir (using {@see Dotenv}, if present).
     *
     * @throws \RuntimeException    When the `KERNEL_CLASS` does not exist
     */
    protected static function getKernelClass(string $workdir): string
    {
        $dotenvFile = $workdir.'/.env.test';

        if ( ! \is_file($dotenvFile) || ! \is_readable($dotenvFile)) {
            throw new \RuntimeException(\sprintf('Cannot load "%s" as it does not exist or is not readable. Skipping.', $dotenvFile));
        }

        // There is no way to override the `ISOLATED_APP_ENV`.
        unset($_SERVER['ISOLATED_APP_ENV'], $_ENV['ISOLATED_APP_ENV']);
        $_SERVER['ISOLATED_APP_ENV'] = $_ENV['ISOLATED_APP_ENV'] = 'test';
        (new Dotenv())
            ->loadEnv($dotenvFile, 'ISOLATED_APP_ENV', 'test');

        if (!isset($_SERVER['KERNEL_CLASS']) && !isset($_ENV['KERNEL_CLASS'])) {
            throw new \RuntimeException(\sprintf('You must set the "KERNEL_CLASS" environment variable to the fully-qualified class name of your application\'s Kernel in "%s" or environment.', $dotenvFile));
        }

        // TODO: Add (external) application's autoloader? Probably required for this to work with an external class.
        //  Would it further be possible to add the new autoloader to the internal one?
        if (!\class_exists($class = $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'])) {
            throw new \RuntimeException(\sprintf('Class "%s" doesn\'t exist or cannot be autoloaded. Check that the "KERNEL_CLASS" value in "%s" matches the fully-qualified class name of your application\'s Kernel.', $class, $dotenvFile));
        }

        return $class;
    }

    /**
     * Creates the kernel from the current workdir (`\getcwd()`), if required conditions apply.
     *  Returns silently, if no instantiation is possible.
     *
     * Available options:
     * - environment
     * - debug
     */
    protected static function createKernel(string $workdir, array $options = []): ?KernelInterface
    {
        static $class;

        try {
            $class ??= static::getKernelClass($workdir);
        } catch (\RuntimeException) {
            // No `.env.test` file found or `KERNEL_CLASS` not defined in current environment (or external dotenv file).
            return null;
        }

        $env   = $options['environment'] ?? $_ENV['APP_ENV']   ?? $_SERVER['APP_ENV']   ?? 'test';
        $debug = $options['debug']       ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new $class($env, (bool)$debug);
    }
}
