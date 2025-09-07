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

use Machinateur\TwigBlockValidator\Annotator\TwigBlockAnnotator;
use Machinateur\TwigBlockValidator\TwigBlockValidatorOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

class TwigBlockAnnotateCommand extends AbstractConsoleCommand
{
    final public const DEFAULT_NAME = 'twig:block:annotate';

    public function __construct(
        private readonly TwigBlockAnnotator $annotator,
        TwigBlockValidatorOutput            $output,
        Environment                         $platformTwig,
        ?string                             $name = null,
    ) {
        parent::__construct($output, $platformTwig, $name);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Annotate block versions in twig templates')
            //->setHelp()
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Silently approve annotating template in-place')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output->init($input, $output);

        $templatePaths = (array)$input->getOption('template');
        $annotatePaths = (array)$input->getOption('validate');
        $version       = $input->getOption('use-version');

        $templatePaths = $this->resolveNamespaces($templatePaths);
        $annotatePaths = $this->resolveNamespaces($annotatePaths);

        if ( ! $this->output->getConsole()
            ->confirm("To annotate the templates in-place can lead to permanent loss of data!\n Continue?" , (bool)$input->getOption('yes'))
        ) {
            return Command::FAILURE;
        }

        // Fallback to platform twig paths, if none are given.
        if (0 === \count($templatePaths)) {
            $templatePaths = $this->getPlatformTemplatePaths();
        }
        // Fallback to version injected from shopware.
        if (null === $version) {
            $version = $this->getVersion();
        } elseif (false === $version) {
            $version = null;
        }

        $this->annotator->annotate($annotatePaths, $templatePaths, $version);

        $this->output->reset();

        return Command::SUCCESS;
    }
}
