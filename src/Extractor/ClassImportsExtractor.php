<?php declare(strict_types=1);

namespace Apilyser\Extractor;

use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;
use Symfony\Component\Console\Output\OutputInterface;

class ClassImportsExtractor
{

    public function __construct(
        private OutputInterface $output,
        private NodeDumper $dumper,
        private NodeFinder $nodeFinder
    ) {}

    public function extract(array $stmts)
    {
        return $this->parseNamespaces($stmts);
    }

    /**
     * @param \PhpParser\Node\Stmt[] $stmts
     * 
     * @return string[]
     */
    private function parseNamespaces(array $stmts): array
    {
        $imports = [];
        $useNamespaces = $this->nodeFinder->findInstanceOf($stmts, Use_::class);

        foreach ($useNamespaces as $useNamespace) {
            foreach ($useNamespace->uses as $use) {
                $this->output->writeln("IMPORT: " . $this->dumper->dump($use));
                $alias = $use->alias ? $use->name->toString() : $use->name->getLast();
                $imports[$alias] = $use->name->name;
            }
        }

        return $imports;
    }
}