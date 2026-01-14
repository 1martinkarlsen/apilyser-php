<?php declare(strict_types=1);

namespace Apilyser\Extractor;

use Apilyser\Parser\NodeParser;
use Apilyser\Resolver\NamespaceResolver;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;
use Symfony\Component\Console\Output\OutputInterface;

class MethodParameterExtractor
{
    public function __construct(
        private OutputInterface $output,
        private NodeDumper $dumper,
        private NamespaceResolver $namespaceResolver
    ) {}

    /**
     * Loops through params and parses each parameter
     * 
     * @param ClassMethod $method
     * @param string[] $imports
     * 
     * @return MethodParam[]
     */
    public function extract(ClassMethod $method, array $imports): array
    {
        $params = $method->params;

        if (empty($params)) {
            return [];
        }

        $requestParams = [];
        echo "Extracting params from method: " . $method->name->name . "\n";
        foreach ($params as $param) {
            $definition = $this->extractParam(param: $param, imports: $imports);
            if ($definition != null) {
                $requestParams[] = $definition;
            }
        }

        return $requestParams;
    }

    /**
     * @return MethodParam|null
     */
    private function extractParam(Param $param, array $imports): ?MethodParam
    {
        $varName = $this->findRequestPropertyName($param);

        if ($varName == null) {
            // Cannot get variable name
            return null;
        }

        switch (true) {
            case $param->type instanceof Identifier:
                // This means that the property is non namespaced object (e.g. 'string', 'int')
                return new MethodParam(
                    name: $varName,
                    type: $param->type->name,
                    isBuiltinType: true
                );
            
            case $param->type instanceof Name:
                // This means that the propertye is a namespaced object (e.g. Response)
                $paramTypeName = null;
                if (null !== $param->type->name) {
                    $paramTypeName = $param->type->name;
                } else {
                    $paramTypeName = $param->type->getParts()[0];
                }

                return new MethodParam(
                    name: $varName,
                    type: $paramTypeName,
                    isBuiltinType: false,
                    fullNamespace: $this->namespaceResolver->findFullNamespaceForClass($paramTypeName, $imports)
                );

            default:
                break;
        }

        return null;
    }

    private function findRequestPropertyName(Param $param): string|null
    {
        if ($param->var instanceof Variable) {
            return $param->var->name;
        } else {
            return null;
        }
    }
}