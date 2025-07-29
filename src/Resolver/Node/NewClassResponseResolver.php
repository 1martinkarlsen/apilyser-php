<?php declare(strict_types=1);

namespace Apilyser\Resolver\Node;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Definition\NewClassResponseParameter;
use Apilyser\Parser\Api\ApiParser;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Resolver\NamespaceResolver;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Resolver\TypeStructureResolver;
use Exception;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;

class NewClassResponseResolver implements ResponseNodeResolver
{

    public function __construct(
        private NamespaceResolver $namespaceResolver,
        private TypeStructureResolver $typeStructureResolver,
        private HttpDelegate $httpDelegate
    ) {}

    public function canResolve(Node $node): bool
    {
        return $node instanceof New_;
    }

    public function resolve(ClassMethodContext $context, Node $node, ?ResponseCall $modifierResponseCall = null): ?ResponseCall
    {
        if (!$node instanceof New_ || !$node->class instanceof Name) {
            throw new Exception("Invalid node type");
        }

        $className = $node->class->name;
        $fullClassName = $this->namespaceResolver->findFullNamespaceForClass($className, $context->imports);

        $responseParser = $this->getResponseParser($fullClassName);
        if (!$responseParser) {
            throw new Exception("Response parser not found");
        }

        $parameters = $responseParser->getNewClassParameters();
        return $this->getResponse(
            context: $context,
            class: $node,
            parameterInfo: $parameters
        );
    }

    private function getResponseParser(string $fullClassName): ?ApiParser
    {
        // Find the http parser for this response class.
        $httpParsers = $this->httpDelegate->getParsers();
        foreach ($httpParsers as $httpParser) {
            if ($httpParser->supportResponseClass($fullClassName)) {
                return $httpParser;
            }
        }

        return null;
    }

    function getResponse(
        ClassMethodContext $context,
        New_ $class, 
        NewClassResponseParameter $parameterInfo
    ): ?ResponseCall
    {
        $statusCode = null;
        $body = null;
        
        foreach ($class->args as $index => $arg)
        {
            if ($arg->name != null) {
                // Named parameters
                if ($arg->name->name == $parameterInfo->bodyName) {
                    $body = $this->typeStructureResolver->resolveFromExpression(
                        context: $context,
                        expr: $arg->value
                    );
                } else if ($arg->name->name == $parameterInfo->statusCodeName) {
                    if ($arg->value instanceof Int_) {
                        $statusCode = $arg->value->value;
                    }
                }
            } else {
                // Positional parameters
                if ($index == $parameterInfo->bodyIndex) {
                    // Body
                    $body = $this->typeStructureResolver->resolveFromExpression(
                        context: $context,
                        expr: $arg->value
                    );
                } else if ($index == $parameterInfo->statusCodeIndex) {
                    // Status code
                    if ($arg->value instanceof Int_) {
                        $statusCode = $arg->value->value;
                    }
                }
            }
        }

        return new ResponseCall(
            type: 'application/json',
            structure: $body,
            statusCode: $statusCode
        );
    }

}