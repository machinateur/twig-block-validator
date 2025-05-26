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
    private readonly string    $workdir;

    protected ?KernelInterface $kernel = null;

    protected static ?string $shopwareVersion = null;

    public function __construct(?string $workdir = null, string $environment = 'prod', bool $debug = false)
    {
        parent::__construct($environment, $debug);

        if (null !== $workdir && ! \is_dir($workdir)) {
            throw new \InvalidArgumentException(\sprintf('The workdir "%s" does not exist', $workdir));
        }

        $this->workdir = $workdir ?? \getcwd();
        // Initialize the kernel directly, as it is needed for the `build()` step of our own container.

        $this->getKernel()
            ?->boot();
    }

    public function getWorkdir(): string
    {
        return $this->workdir;
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/../..';
    }

    public function getCacheDir(): string
    {
        return $this->getWorkdir().'/.twig-block-validator';
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
        // Make sure the parent class is used here still.
        $class = parent::class;
        $class = \str_contains($class, "@anonymous\0") ? \get_parent_class($class).\str_replace('.', '_', ContainerBuilder::hash($class)) : $class;
        $class = \str_replace('\\', '_', $class).\ucfirst($this->environment).($this->debug ? 'Debug' : '').'Container';

        if (!\preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class)) {
            throw new \InvalidArgumentException(\sprintf('The environment "%s" contains invalid characters, it can only contain characters allowed in PHP class names.', $this->environment));
        }

        return $class;
    }

    /**
     * Factory method.
     *
     * Internally creates the external kernel in `test` environment and `debug` set to `true`.
     */
    public function getKernel(): ?KernelInterface
    {
        if ($this->kernel) {
            return $this->kernel;
        }

        $kernel = BoxKernel::isPhar() ? $this->createKernel($this->workdir, [
            'environment' => 'test',
            'debug'       => true,
        ]) : null;

        return $this->kernel = $kernel;
    }

    public function boot(): void
    {
        $this->getKernel()
            ?->boot();

        parent::boot();
    }

    public function reboot(?string $warmupDir): void
    {
        parent::reboot($warmupDir);
    }

    public function shutdown(): void
    {
        parent::shutdown();

        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if ( ! $this->kernel) {
            return;
        }

        try {
            static::$shopwareVersion = $this->kernel?->getContainer()
                ->getParameter('kernel.shopware_version');
        } catch (\Throwable $e) {
            // no-op
        }

        try {
            $twigDefaultPath = $this->kernel->getContainer()
                ->getParameter('twig.default_path');

            $container->setParameter('twig.default_path', $twigDefaultPath);
        } catch (\Throwable $e) {
            throw new \UnexpectedValueException('Failed to set platform default twig path. ' . $e->getMessage(), previous: $e);
        }

        try {
            // Copy over twig service from application kernel.
            $container->set('twig',
                // Get twig, even though it's private, by leveraging the built-in test container.
                $this->kernel?->getContainer()
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
    protected static function getShopwareVersion(): ?string
    {
        return self::$shopwareVersion;
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
     *  Returns `null` silently, if no instantiation is possible.
     *
     * Available options:
     * - environment
     * - debug
     */
    protected function createKernel(string $workdir, array $options = []): ?KernelInterface
    {
        static $class;

        try {
            $class ??= static::getKernelClass($workdir);
        } catch (\RuntimeException) {
            // No `.env.test` file found or `KERNEL_CLASS` not defined in ENV.
            return null;
        }

        $env   = $options['environment'] ?? $_ENV['APP_ENV']   ?? $_SERVER['APP_ENV']   ?? 'test';
        $debug = $options['debug']       ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new $class($env, (bool)$debug);
    }
}
