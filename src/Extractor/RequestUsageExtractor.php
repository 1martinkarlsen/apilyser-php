<?php

namespace Apilyser\Extractor;

use Apilyser\Definition\RequestType;
use Apilyser\Parser\Api\ApiParser;
use Apilyser\Traverser\VariableUsageTraverser;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

class RequestUsageExtractor
{

    public function __construct(private ApiParser $apiParser) {}

    /**
     * @param Node\Stmt[] $methodStmts
     * @param string $paramName
     * 
     * @return RequestCall[]
     */
    public function findCalls(array $methodStmts, string $paramName): array
    {
        $calls = $this->traverseRequestUsage($methodStmts, $paramName);

        $params = [];

        foreach ($calls as $call) {
            $result = $this->handleBodyOrQueryExpression($call);
            if ($result != null) {
                $result->variableName = $paramName;
                array_push($params, $result);
            }
        }

        return $params;
    }

    private function handleBodyOrQueryExpression(Node $node): ?RequestCall
    {
        switch (true) {
            case $node instanceof Expression:
                return $this->handleBodyOrQueryExpression($node->expr);

            case $node instanceof Assign:
                return $this->handleBodyOrQueryExpression($node->expr);

            case $node instanceof MethodCall:
                return $this->handleMethodCall($node);
        }

        return null;
    }

    private function handleMethodCall(MethodCall $method): ?RequestCall
    {
        $expr = $method->var;
        $location = RequestType::Unknown;

        if ($expr instanceof PropertyFetch) {
            if ($expr->var instanceof Variable) {
                switch ($expr->name->name) {
                    case $this->apiParser->getQuery():
                        $location = RequestType::Query;
                        break;
                    case $this->apiParser->getBody():
                        $location = RequestType::Body;
                        break;
                    default:
                        break;
                }
            
            }
        }

        $arg = $method->args[0];
        $valueName = null;
        $valueType = null;
        if ($arg->value instanceof String_) {
            $valueName = $arg->value->value;
            $valueType = "string";
        } else if ($arg->value instanceof Int_) {
            $valueName = $arg->value->value;
            $valueType = "int";
        } else if ($arg->value instanceof Float_) {
            $valueName = $arg->value->value;
            $valueType = "float";
        } else {
            $valueType = null;
        }

        $result = new RequestCall();
        $result->parameterName = $valueName;
        $result->source = $location;
        $result->deducedType = $valueType;
        $result->node = $method;

        return $result;
    }

    /**
     * @return Node[]
     */
    private function traverseRequestUsage(array $stmts, string $name): array
    {
        // Establish parent relationships
        $traverser = new NodeTraverser();
        $parentConnector = new ParentConnectingVisitor();
        $traverser->addVisitor($parentConnector);
        $ast = $traverser->traverse($stmts);

        // Find variable usages
        $tt = new NodeTraverser();
        $usageFinder = new VariableUsageTraverser($name);
        $tt->addVisitor($usageFinder);
        $tt->traverse($ast);
        $usages = $usageFinder->getUsages();

        return $usages;
    }
}