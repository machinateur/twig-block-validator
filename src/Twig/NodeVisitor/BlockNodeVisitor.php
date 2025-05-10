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

namespace Machinateur\Shopware\TwigBlockValidator\Twig\NodeVisitor;

use Machinateur\Shopware\TwigBlockValidator\Twig\Node\ShopwareBlockCollectionNode;
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
    private ?BlockNode $block = null;


    protected ShopwareBlockCollectionNode $collection;

    public function __construct(?ShopwareBlockCollectionNode $collection = null, private ?string $defaultVersion = null)
    {
        $this->collection = $collection ?? new ShopwareBlockCollectionNode();
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $metaNodeExists = $node->getNode('class_end')
                ->hasNode('sw_block_collection');

            if ( ! $metaNodeExists) {
                $node->getNode('class_end')
                    ->setNode('sw_block_collection', $this->collection);
            }

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
        }

        if ($node instanceof BlockNode) {
            $name = $node->getAttribute('name');

            $this->collection->pushBlockStack($name,
                (int)$node->getAttribute('line_no_start'),
                (int)$node->getAttribute('line_no_end'),
            );
        }

        // Only use nodes that are not "exposed", and thus are marked as real comments, not usages of the tag.
        if ($node instanceof CommentNode && ! $node->exposed) {
            //$comment = $node->getAttribute('text');
            $this->collection->addComment($node->text, $this->defaultVersion);
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

    public function getCollection(): ShopwareBlockCollectionNode
    {
        return $this->collection;
    }

    public function setCollection(ShopwareBlockCollectionNode $collection): void
    {
        $this->collection = $collection;
    }

    public function resetCollection(): ShopwareBlockCollectionNode
    {
        $collection = $this->getCollection();
        $this->setCollection(new ShopwareBlockCollectionNode());
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
}
