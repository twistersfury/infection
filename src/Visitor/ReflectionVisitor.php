<?php
/**
 * Copyright © 2017 Maks Rafalko
 *
 * License: https://opensource.org/licenses/BSD-3-Clause New BSD License
 */
declare(strict_types=1);

namespace Infection\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ReflectionVisitor extends NodeVisitorAbstract
{
    const REFLECTION_CLASS_KEY = 'reflectionClass';
    const IS_INSIDE_FUNCTION_KEY = 'isInsideFunction';
    const FUNCTION_SCOPE_KEY = 'functionScope';

    private $scopeStack = [];

    /**
     * @var \ReflectionClass
     */
    private $classReflection;

    public function beforeTraverse(array $nodes)
    {
        $this->scopeStack = [];
        $this->classReflection = null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassLike) {
            $reflectionClass = new \ReflectionClass($node->fullyQualifiedClassName->toString());

            $this->classReflection = $reflectionClass;
        }

        $isInsideFunction = $this->isInsideFunction($node);

        if ($isInsideFunction) {
            $node->setAttribute(self::IS_INSIDE_FUNCTION_KEY, true);
        }

        if ($this->isFunctionLikeNode($node)) {
            $this->scopeStack[] = $node;
            $node->setAttribute(self::REFLECTION_CLASS_KEY, $this->classReflection);
        } elseif ($isInsideFunction) {
            $node->setAttribute(self::FUNCTION_SCOPE_KEY, $this->scopeStack[count($this->scopeStack) - 1]);
            $node->setAttribute(self::REFLECTION_CLASS_KEY, $this->classReflection);
        }
    }

    public function leaveNode(Node $node)
    {
        if ($this->isFunctionLikeNode($node)) {
            array_pop($this->scopeStack);
        }
    }

    /**
     * Recursively determine whether the node is inside the function
     *
     * @param Node $node
     *
     * @return bool
     */
    private function isInsideFunction(Node $node): bool
    {
        if (!$node->hasAttribute(ParentConnectorVisitor::PARENT_KEY)) {
            return false;
        }

        $parent = $node->getAttribute(ParentConnectorVisitor::PARENT_KEY);

        if ($parent->getAttribute(self::IS_INSIDE_FUNCTION_KEY)) {
            return true;
        }

        if ($this->isFunctionLikeNode($parent)) {
            return true;
        }

        return $this->isInsideFunction($parent);
    }

    private function isFunctionLikeNode(Node $node): bool
    {
        $isFunction = $node instanceof Node\Stmt\Function_;
        $isClassMethod = $node instanceof Node\Stmt\ClassMethod;
        $isClosure = $node instanceof Node\Expr\Closure;

        return $isFunction || $isClassMethod || $isClosure;
    }
}
