<?php
/**
 * Copyright © 2017-2018 Maks Rafalko
 *
 * License: https://opensource.org/licenses/BSD-3-Clause New BSD License
 */

declare(strict_types=1);

namespace Infection\Mutator\Arithmetic;

use Infection\Mutator\Util\Mutator;
use PhpParser\Node;

class AssignmentEqual extends Mutator
{
    /**
     * Replaces "==" with a "=".
     *
     * @param Node $node
     *
     * @return Node\Expr\Assign
     */
    public function mutate(Node $node)
    {
        return new Node\Expr\Assign($node->left, $node->right, $node->getAttributes());
    }

    protected function mutatesNode(Node $node): bool
    {
        return $node instanceof Node\Expr\BinaryOp\Equal && $node->left instanceof Node\Expr\Variable;
    }
}
