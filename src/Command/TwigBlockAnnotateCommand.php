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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TwigBlockAnnotateCommand extends Command
{
    use ConsoleCommandTrait;

    public const DEFAULT_NAME = 'twig:block:annotate';

    /**
     * @param string|null $name     The override command name. This is useful for adding it as composer script.
     */
    public function __construct(
        private readonly TwigBlockAnnotator       $annotator,
        private readonly TwigBlockValidatorOutput $output,
        ?string                                   $name = null,
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
            ->addOption('template', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Twig template path to load')
            ->addOption('validate', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Twig template path to validate')
            ->addOption('use-version', 'r', InputOption::VALUE_OPTIONAL, 'The version number required')
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

        $this->annotator->annotate($annotatePaths, $templatePaths, $version);

        $this->output->reset();

        return Command::SUCCESS;
    }
}
