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
    protected readonly string  $workdir;
    protected ?KernelInterface $kernel = null;

    public function __construct(string $environment = 'prod', bool $debug = false, ?string $workdir = null)
    {
        parent::__construct($environment, $debug);

        if (null !== $workdir && ! \is_dir($workdir)) {
            throw new \InvalidArgumentException(\sprintf('The workdir "%s" does not exist', $workdir));
        }

        $this->workdir = $workdir ?? \getcwd();
        $this->kernel  = $this->getKernel();
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

    public function getKernel(): KernelInterface
    {
        if ($this->kernel) {
            return $this->kernel;
        }

        // Resolve and boot the application kernel.
        $kernel = $this->createKernel([
            'environment' => 'test',
            'debug'       => $this->debug,
        ]);
        $kernel->boot();

        return $this->kernel = $kernel;
    }

    public function boot(): void
    {
        $this->kernel ??= $this->getKernel();
        $this->kernel?->boot();

        parent::boot();
    }

    public function reboot(?string $warmupDir): void
    {
        parent::reboot($warmupDir);

        if ( ! $this->kernel) {
            return;
        }

        $this->kernel->reboot($warmupDir);
    }

    public function shutdown(): void
    {
        parent::shutdown();

        if ( ! $this->kernel) {
            return;
        }

        // Shut down the application kernel.
        $this->kernel->shutdown();
        $this->kernel = null;
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if ( ! $this->kernel) {
            return;
        }

        try {
            $container->setParameter('twig.default_path',
                $this->kernel?->getContainer()
                    ->getParameter('twig.default_path')
            );
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
    public static function isPhar(): bool
    {
        return '' !== \Phar::running(false);
    }

    /**
     * @throws \RuntimeException
     * @throws \LogicException
     */
    protected function getKernelClass(): string
    {
        // There is no way to override the ISOLATED_APP_ENV.
        unset($_SERVER['ISOLATED_APP_ENV'], $_ENV['ISOLATED_APP_ENV']);
        $_SERVER['ISOLATED_APP_ENV'] = $_ENV['ISOLATED_APP_ENV'] = 'test';
        (new Dotenv())
            ->loadEnv($this->workdir.'/.env.test', 'ISOLATED_APP_ENV', 'test');

        if (!isset($_SERVER['KERNEL_CLASS']) && !isset($_ENV['KERNEL_CLASS'])) {
            throw new \LogicException(\sprintf('You must set the KERNEL_CLASS environment variable to the fully-qualified class name of your Kernel in phpunit.xml / phpunit.xml.dist or override the "%1$s::createKernel()" or "%1$s::getKernelClass()" method.', static::class));
        }

        if (!class_exists($class = $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'])) {
            throw new \RuntimeException(\sprintf('Class "%s" doesn\'t exist or cannot be autoloaded. Check that the KERNEL_CLASS value in phpunit.xml matches the fully-qualified class name of your Kernel or override the "%s::createKernel()" method.', $class, static::class));
        }

        return $class;
    }

    /**
     * Creates a Kernel.
     *
     * Available options:
     *
     *  * environment
     *  * debug
     */
    protected function createKernel(array $options = []): KernelInterface
    {
        static $class;
        $class ??= static::getKernelClass();

        $env   = $options['environment'] ?? $_ENV['APP_ENV']   ?? $_SERVER['APP_ENV']   ?? 'test';
        $debug = $options['debug']       ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new $class($env, (bool)$debug);
    }
}
