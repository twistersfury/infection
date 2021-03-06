<?php
/**
 * Copyright © 2017-2018 Maks Rafalko
 *
 * License: https://opensource.org/licenses/BSD-3-Clause New BSD License
 */

declare(strict_types=1);

namespace Infection\Tests\Visitor;

use Infection\Visitor\FullyQualifiedClassNameVisitor;
use Infection\Visitor\ParentConnectorVisitor;
use Infection\Visitor\ReflectionVisitor;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class ReflectionVisitorTest extends TestCase
{
    private $spyVisitor;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var string
     */
    private $code;

    protected function setUp()
    {
        $this->spyVisitor = $this->getInsideFunctionSpyVisitor();

        $lexer = new Lexer\Emulative([
            'usedAttributes' => [
                'comments', 'startLine', 'endLine', 'startTokenPos', 'endTokenPos', 'startFilePos', 'endFilePos',
            ],
        ]);

        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);
        $this->code = $this->getFileContent('rv-part-of-signature-flag.php');
    }

    /**
     * @dataProvider isPartOfSignatureFlagProvider
     */
    public function test_it_sets_is_part_of_signature_flag(string $nodeClass, bool $expectedResult)
    {
        $statements = $this->parser->parse($this->code);

        $traverser = new NodeTraverser();
        $spyVisitor = $this->getSpyVisitor($nodeClass);

        $traverser->addVisitor(new ParentConnectorVisitor());
        $traverser->addVisitor(new FullyQualifiedClassNameVisitor());
        $traverser->addVisitor(new ReflectionVisitor());
        $traverser->addVisitor($spyVisitor);

        $traverser->traverse($statements);

        $this->assertSame($expectedResult, $spyVisitor->isPartOfSignature());
    }

    public function test_it_detects_if_traversed_inside_class_method()
    {
        $code = $this->getFileContent('rv-inside-class-method.php');

        $this->parseAndTraverse($code);

        $this->assertTrue($this->spyVisitor->isInsideFunction);
    }

    public function test_it_detects_if_traversed_inside_function()
    {
        $code = $this->getFileContent('rv-inside-function.php');

        $this->parseAndTraverse($code);

        $this->assertFalse($this->spyVisitor->isInsideFunction);
    }

    public function test_it_detects_if_traversed_inside_closure()
    {
        $code = $this->getFileContent('rv-inside-closure.php');

        $this->parseAndTraverse($code);

        $this->assertTrue($this->spyVisitor->isInsideFunction);
    }

    public function test_it_does_not_add_inside_function_flag_if_not_needed()
    {
        $code = $this->getFileContent('rv-without-function.php');

        $this->parseAndTraverse($code);

        $this->assertFalse($this->spyVisitor->isInsideFunction);
    }

    public function test_it_correctly_works_with_anonymous_classes()
    {
        $code = $this->getFileContent('rv-anonymous-class.php');

        $this->parseAndTraverse($code);

        $this->assertTrue($this->spyVisitor->isInsideFunction);
    }

    public function test_it_sets_reflection_class_to_nodes()
    {
        $code = $this->getFileContent('rv-inside-class-method.php');
        $reflectionSpyVisitor = $this->getReflectionClassSpyVisitor();

        $this->parseAndTraverse($code, $reflectionSpyVisitor);

        $this->assertInstanceOf(\ReflectionClass::class, $reflectionSpyVisitor->reflectionClass);
        $this->assertSame(\InfectionReflectionClassMethod\Foo::class, $reflectionSpyVisitor->reflectionClass->getName());
    }

    public function isPartOfSignatureFlagProvider(): array
    {
        return [
            [Node\Stmt\ClassMethod::class, true],
            [Node\Param::class, true], // $param
            [Node\Scalar\DNumber::class, true], // 2.0
            [Node\Scalar\LNumber::class, false], // 1
            [Node\Expr\BinaryOp\Identical::class, false], // ===
            [Node\Arg::class, false],
            [Node\Expr\Array_::class, false], // []
        ];
    }

    private function getSpyVisitor(string $nodeClass): NodeVisitorAbstract
    {
        return new class($nodeClass) extends NodeVisitorAbstract {
            /** @var string */
            private $nodeClassUnderTest;

            private $isPartOfSignature;

            public function __construct(string $nodeClass)
            {
                $this->nodeClassUnderTest = $nodeClass;
            }

            public function leaveNode(Node $node)
            {
                if ($node instanceof $this->nodeClassUnderTest) {
                    $this->isPartOfSignature = $node->getAttribute(ReflectionVisitor::IS_ON_FUNCTION_SIGNATURE, false);
                }
            }

            public function isPartOfSignature()
            {
                return $this->isPartOfSignature;
            }
        };
    }

    private function getNodes(string $code): array
    {
        $lexer = new Lexer\Emulative();
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);

        return $parser->parse($code);
    }

    private function getInsideFunctionSpyVisitor()
    {
        return new class() extends NodeVisitorAbstract {
            public $isInsideFunction = false;

            public function enterNode(Node $node)
            {
                if ($node->hasAttribute(ReflectionVisitor::IS_INSIDE_FUNCTION_KEY)) {
                    $this->isInsideFunction = true;

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
            }
        };
    }

    private function getReflectionClassSpyVisitor()
    {
        return new class() extends NodeVisitorAbstract {
            /** @var \ReflectionClass */
            public $reflectionClass;

            public function enterNode(Node $node)
            {
                if ($node->hasAttribute(ReflectionVisitor::REFLECTION_CLASS_KEY)) {
                    $this->reflectionClass = $node->getAttribute(ReflectionVisitor::REFLECTION_CLASS_KEY);

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
            }
        };
    }

    private function parseAndTraverse(string $code, NodeVisitor $nodeVisitor = null)
    {
        $nodes = $this->getNodes($code);

        $traverser = new NodeTraverser();

        $traverser->addVisitor(new ParentConnectorVisitor());
        $traverser->addVisitor(new FullyQualifiedClassNameVisitor());
        $traverser->addVisitor(new ReflectionVisitor());
        $traverser->addVisitor($nodeVisitor ?: $this->spyVisitor);

        $traverser->traverse($nodes);
    }

    private function getFileContent(string $file): string
    {
        return file_get_contents(sprintf(__DIR__ . '/../Fixtures/Autoloaded/Reflection/%s', $file));
    }
}
