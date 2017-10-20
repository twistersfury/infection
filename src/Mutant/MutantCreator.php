<?php
/**
 * Copyright © 2017 Maks Rafalko
 *
 * License: https://opensource.org/licenses/BSD-3-Clause New BSD License
 */
declare(strict_types=1);

namespace Infection\Mutant;

use Infection\Differ\Differ;
use Infection\Mutation;
use Infection\TestFramework\Coverage\CodeCoverageData;
use Infection\Visitor\MutatorVisitor;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

class MutantCreator
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var Differ
     */
    private $differ;

    public function __construct(string $tempDir, Differ $differ)
    {
        $this->tempDir = $tempDir;
        $this->differ = $differ;
    }

    public function create(Mutation $mutation, CodeCoverageData $codeCoverageData): Mutant
    {
        $lexer = new Lexer\Emulative([
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
                'startFilePos', 'endFilePos',
            ],
        ]);
        $parser = new Parser\Php7($lexer);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\CloningVisitor());

        $mutatorVisitor = new MutatorVisitor($mutation);
        $traverser->addVisitor($mutatorVisitor);

        $printer = new Standard();

        $originalCode = file_get_contents($mutation->getOriginalFilePath());
        $oldStmts = $parser->parse($originalCode);
        $oldTokens = $lexer->getTokens();

        $newStmts = $traverser->traverse($oldStmts);

        $mutatedFilePath = sprintf('%s/mutant.%s.infection.php', $this->tempDir, $mutation->getHash());

        $isCoveredByTest = $this->isCoveredByTest($mutation, $codeCoverageData);

        $mutatedCode = $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);

        file_put_contents($mutatedFilePath, $mutatedCode);

        $diff = $this->differ->diff($originalCode, $mutatedCode);

        return new Mutant(
            $mutatedFilePath,
            $mutation,
            $diff,
            $isCoveredByTest,
            $codeCoverageData->getAllTestsFor($mutation)
        );
    }

    private function isCoveredByTest(Mutation $mutation, CodeCoverageData $codeCoverageData): bool
    {
        $mutator = $mutation->getMutator();
        $line = $mutation->getAttributes()['startLine'];
        $filePath = $mutation->getOriginalFilePath();

        if ($mutator->isFunctionBodyMutator()) {
            return $codeCoverageData->hasTestsOnLine($filePath, $line);
        }

        if ($mutator->isFunctionSignatureMutator()) {
            return $codeCoverageData->hasExecutedMethodOnLine($filePath, $line);
        }

        return false;
    }
}
