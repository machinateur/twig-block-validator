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

namespace Machinateur\TwigBlockValidator\Twig\NodeVisitor;

use Machinateur\TwigBlockValidator\Twig\Node\CommentCollectionNode;
use Machinateur\Twig\Node\CommentNode;
use Twig\Environment;
use Twig\Node\BlockNode;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * A node-visitor to collect the block-hash comments parsed from the template code's comments.
 */
class BlockNodeVisitor implements NodeVisitorInterface
{
    /**
     * @var array<CommentNode>
     */
    private array $comments = [];

    protected CommentCollectionNode $collection;

    public function __construct(?CommentCollectionNode $collection = null, private ?string $defaultVersion = null)
    {
        $this->collection = $collection ?? new CommentCollectionNode();
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            // Only supports constant parent expressions.
            if ($node->hasNode('parent')
                /** @var AbstractExpression $parent */
                && ($parent = $node->getNode('parent')) instanceof ConstantExpression
            ) {
                $parentValue = $parent->getAttribute('value');
                \assert(\is_string($parentValue));
                $this->collection->setParentTemplate($parentValue);
            }

            $this->collection->setTemplate($node->getTemplateName());

            // Reset comments.
            $this->comments = [];
        }

        if ($node instanceof CommentNode) {
            // Save comments, as we encounter them.
            $this->comments[] = $node;
        }

        if ($node instanceof BlockNode) {
            $name = $node->getAttribute('name');

            $this->collection->pushBlockStack($name,
                (int)$node->getAttribute('line_no_start'),
                (int)$node->getAttribute('line_no_end'),
            );

            if ($comment = $this->resolveComment($node)) {
                $this->collection->addComment($comment->text, $this->defaultVersion);
            }
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof ModuleNode) {
            $this->collection->setTemplate(null);
            $this->collection->setParentTemplate(null);
        }

        if ($node instanceof BlockNode) {
            $this->collection->popBlockStack();
        }

        return $node;
    }

    /**
     * Priority `0` is the default.
     */
    public function getPriority(): int
    {
        return 0;
    }

    public function getCollection(): CommentCollectionNode
    {
        return $this->collection;
    }

    public function setCollection(CommentCollectionNode $collection): void
    {
        $this->collection = $collection;
    }

    public function resetCollection(): CommentCollectionNode
    {
        $collection = $this->getCollection();
        $this->setCollection(new CommentCollectionNode());
        return $collection;
    }

    public function getDefaultVersion(): ?string
    {
        return $this->defaultVersion;
    }

    public function setDefaultVersion(?string $defaultVersion): void
    {
        $this->defaultVersion = $defaultVersion;
    }

    protected function resolveComment(BlockNode $block): ?CommentNode
    {
        // Here, we rely on the comments from a `module.body` being visited prior to the `module.blocks` node.
        foreach ($this->comments as $index => $comment) {
            // The comment has to be located exactly oon the line before the block start.
            if (1 !== $block->getTemplateLine() - $comment->getTemplateLine()) {
                continue;
            }


            // Delete the comment from the list, as it was resolved.
            unset($this->comments[$index]);
            // There can only be one comment on the prev-line, so we break out right here.
            return $comment;
        }

        return null;
    }
}
