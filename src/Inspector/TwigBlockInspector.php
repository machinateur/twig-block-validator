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

namespace Machinateur\TwigBlockValidator\Inspector;

use Machinateur\TwigBlockValidator\Event\Inspector\InspectCommentsErrorEvent;
use Machinateur\TwigBlockValidator\Event\Inspector\InspectCommentsEvent;
use Machinateur\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Machinateur\TwigBlockValidator\Twig\Node\CommentCollectionInterface;
use Machinateur\TwigBlockValidator\Twig\Node\CommentCollectionNode;
use Machinateur\TwigBlockValidator\Twig\Node\TwigBlockStackInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Twig\Error\Error as TwigError;

/**
 * @phpstan-import-type _Comment            from CommentCollectionInterface
 * @phpstan-import-type _CommentCollection  from CommentCollectionInterface
 * @phpstan-import-type _Block              from TwigBlockStackInterface
 *
 * @phpstan-import-type _NamespacedPathMap  from BlockValidatorEnvironment
 */
class TwigBlockInspector
{
    public function __construct(
        private readonly BlockValidatorEnvironment $twig,
        private readonly EventDispatcherInterface  $dispatcher,
    ) {
    }

    /**
     * Inspect given paths against the provided templates using the given fallback version.
     *
     * @param _NamespacedPathMap $scopePaths
     * @param _NamespacedPathMap $templatePaths
     * @param string|null        $version
     */
    public function inspect(array $scopePaths, array $templatePaths = [], ?string $version = null): void
    {
        // TODO: Implement.
        //  The challenge with this will likely be getting the twig environment, node visitor and comment collection node
        //   to also collect non-hash comments as well. Filtering should be moved from the collection trait to the validator/inspector class.

        $scopePaths    = \array_map('array_unique', $scopePaths);
        $templatePaths = \array_map('array_unique', $templatePaths);


        // First reset the validator's environment, in case this is called more than once in the same process.
        $this->twig->reset();

        $this->twig->registerPaths($scopePaths);
        if ($templatePaths) {
            $this->twig->registerPaths($templatePaths);
        }

        $nodeVisitor = $this->twig->getBlockNodeVisitor();
        // Get the previous collection instance to restore it after inspection.
        $previousCollection = $nodeVisitor->getCollection();
        $nodeVisitor->setCollection(new CommentCollectionNode());
        // Get the previous default version to restore it after inspection.
        $previousDefaultVersion = $nodeVisitor->getDefaultVersion();
        $nodeVisitor->setDefaultVersion($version);


        /** @var list<TwigError> $errors */
        $errors   = [];
        $comments = $this->twig->loadComments($scopePaths, $errors);

        if (0 < \count($comments)) {
            $this->dispatcher->dispatch(
                $event = new InspectCommentsEvent($comments, $version)
            );

            $event->notify(InspectCommentsEvent::CALL_BEGIN);

            /** @var _CommentCollection $comments */
            foreach ($comments as & $comment) {
                try {
                    $event->notify(InspectCommentsEvent::CALL_STEP, $comment);
                } catch (TwigError $error) {
                    $errors[] = $error;
                }
            }

            $event->notify(InspectCommentsEvent::CALL_END);
        }

        if (0 < \count($errors)) {
            $this->dispatcher->dispatch(
                new InspectCommentsErrorEvent($errors)
            );
        }

        // Restore the collection.
        $nodeVisitor->setCollection($previousCollection);
        // Restore the default version.
        $nodeVisitor->setDefaultVersion($previousDefaultVersion);
    }
}
