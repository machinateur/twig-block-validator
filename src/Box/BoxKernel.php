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

use Machinateur\TwigBlockValidator\Command\TwigBlockAnnotateCommand;
use Machinateur\TwigBlockValidator\Command\TwigBlockValidateCommand;
use Machinateur\TwigBlockValidator\TwigBlockValidatorKernel;
use Symfony\Component\Console\Application;

class BoxKernel extends TwigBlockValidatorKernel
{
    public function __construct(string $environment = 'prod', bool $debug = false)
    {
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/../..';
    }

    /**
     * Create a custom application for the box runtime.
     */
    public function createApplication(): Application
    {
        $container   = $this->getContainer();
        /**
         * @see https://github.com/box-project/box/blob/main/doc/configuration.md#pretty-git-tag-placeholder-git-version
         *
         * @noinspection PhpClassConstantAccessedViaChildClassInspection
         */
        $application = new Application('Twig Block Validator', BoxKernel::BUNDLE_VERSION);
        if (BoxKernel::isPhar()) {
            // Use actual release tag, when running the phar archive.
            $application->setVersion('@box_release_build@');
        }

        $validateCommand = $container->get(TwigBlockValidateCommand::class);
        $validateCommand->setName('validate');
        $annotateCommand = $container->get(TwigBlockAnnotateCommand::class);
        $annotateCommand->setName('annotate');

        $application->addCommands([
            $validateCommand,
            $annotateCommand,
        ]);

        return $application;
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
}
