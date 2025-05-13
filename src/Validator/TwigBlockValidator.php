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

namespace Machinateur\TwigBlockValidator\Validator;

use Composer\Semver\Semver;
use Machinateur\TwigBlockValidator\Event\Validator\ValidateCommentsErrorEvent;
use Machinateur\TwigBlockValidator\Event\Validator\ValidateCommentsEvent;
use Machinateur\TwigBlockValidator\Service\TwigBlockResolver;
use Machinateur\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Machinateur\TwigBlockValidator\Twig\Extension\BlockValidatorExtension;
use Machinateur\TwigBlockValidator\Twig\Node\CommentCollectionInterface;
use Machinateur\TwigBlockValidator\Twig\Node\TwigBlockStackInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Twig\Error\Error as TwigError;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @phpstan-import-type _Comment            from CommentCollectionInterface
 * @phpstan-import-type _CommentCollection  from CommentCollectionInterface
 * @phpstan-import-type _Block              from TwigBlockStackInterface
 *
 * @phpstan-type _ValidatedComment          _Comment&array{
 *     'source_hash'    : string|null,
 *     'source_version' : string|null,
 *     'valid'          : bool,
 * }
 * @phpstan-type _ValidatedCommentCollection  array<_ValidatedComment>
 *
 * @phpstan-import-type _NamespacedPathMap  from BlockValidatorEnvironment
 */
class TwigBlockValidator
{
    public function __construct(
        private readonly BlockValidatorEnvironment $twig,
        private readonly TwigBlockResolver         $blockResolver,
        private readonly EventDispatcherInterface  $dispatcher,
    ) {
    }

    /**
     * Validate given paths against the provided templates using the given fallback version.
     *
     * @param _NamespacedPathMap $scopePaths
     * @param _NamespacedPathMap $templatePaths
     * @param string|null        $version
     */
    public function validate(array $scopePaths, array $templatePaths = [], ?string $version = null): void
    {
        // First reset the validator's environment, in case this is called more than once in the same process.
        $this->twig->reset();

        $this->twig->registerPaths($scopePaths);
        if ($templatePaths) {
            $this->twig->registerPaths($templatePaths);
        }

        $nodeVisitor = $this->twig->getBlockNodeVisitor();
        // Get the previous default version to restore it after validation.
        $defaultVersion = $nodeVisitor->getDefaultVersion();
        $nodeVisitor->setDefaultVersion($version);

        /** @var list<TwigError> $errors */
        $errors   = [];
        $comments = $this->twig->loadComments($scopePaths, $errors);

        if (0 < \count($comments)) {
            $this->dispatcher->dispatch(
                $event = new ValidateCommentsEvent($comments, $version)
            );

            $event->notify(ValidateCommentsEvent::CALL_BEGIN);

            /** @var _ValidatedCommentCollection $comments */
            foreach ($comments as & $comment) {
                try {
                    $this->validateComment($comment, $version);

                    $event->notify(ValidateCommentsEvent::CALL_STEP, $comment);
                } catch (TwigError $error) {
                    $errors[] = $error;
                }
            }

            $event->notify(ValidateCommentsEvent::CALL_END);
        }

        if (0 < \count($errors)) {
            $this->dispatcher->dispatch(
                new ValidateCommentsErrorEvent($errors)
            );
        }

        // Reset the default version.
        $nodeVisitor->setDefaultVersion($defaultVersion);
    }

    /**
     * Validate a single comment for a block. Shortcut method, internal logic.
     *
     * @param _Comment|_ValidatedComment $comment
     *
     * @throws LoaderError      when the block cannot be resolved or the template does not exist
     * @throws RuntimeError     when the generated code is erroneous
     * @throws SyntaxError      when there is a syntax error, like missing tags
     */
    protected function validateComment(array & $comment, ?string $defaultVersion): bool
    {
        $template       = $comment['template'];
        $blockName      = $comment['block'];
        $hash           = $comment['hash'];
        $version        = $comment['version'];

        // Enrich the comment, i.e. _ValidatedComment.
        $comment['source_hash']    = $sourceHash = $this->blockResolver->getSourceHash($template, $blockName);
        $comment['source_version'] = $defaultVersion;

        $matchHash    = $hash === $sourceHash;
        $matchVersion = Semver::satisfies($version, '~'.$defaultVersion);

        $comment['match'] = [
            'hash'    => $matchHash,
            'version' => $matchVersion,
        ];

        return $comment['valid'] = ($matchHash && $matchVersion);
    }
}
