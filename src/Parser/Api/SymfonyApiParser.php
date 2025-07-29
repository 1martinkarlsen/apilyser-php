<?php declare(strict_types=1);

namespace Apilyser\Parser\Api;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Definition\NewClassResponseParameter;
use Apilyser\Resolver\NamespaceResolver;
use Apilyser\Resolver\ResponseCall;
use PhpParser\Node;
use Apilyser\Resolver\TypeStructureResolver;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SymfonyApiParser implements ApiParser {

    private const QUERY = "query";
    private const BODY = "body";
    private const RESPONSE_STATUS_NAME = "status";
    private const RESPONSE_BODY_NAME = "data";
    private const RESPONSE_STATUS_INDEX = 1;
    private const RESPONSE_BODY_INDEX = 0;
    private const RESPONSE_FUNCTION_SET_STATUS_CODE = "setStatusCode";
    private const RESPONSE_FUNCTION_SET_CONTENT = "setContent";
    private const RESPONSE_FUNCTION_SET_DATA = "setData";
    private const RESPONSE_FUNCTION_SEND = "send";

    private array $requestClasses = [
        Request::class
    ];

    private array $responseClasses = [
        JsonResponse::class,
        Response::class
    ];

    public function __construct(
        private OutputInterface $output,
        private NamespaceResolver $namespaceResolver,
        private TypeStructureResolver $typeStructureResolver
    ) {}


    function getSupportedResponseClasses(): array
    {
        return $this->responseClasses;
    }

    function getSupportedRequestClasses(): array
    {
        return $this->requestClasses;
    }

    function supportRequestClass(string $className): bool
    {
        return in_array($className, $this->requestClasses);
    }

    function supportResponseClass(string $className): bool
    {
        return in_array($className, $this->responseClasses);
    }

    function getQuery(): string
    {
        return self::QUERY;
    }

    function getBody(): string
    {
        return self::BODY;
    }

    function getNewClassParameters(): NewClassResponseParameter
    {
        return new NewClassResponseParameter(
            statusCodeName: self::RESPONSE_STATUS_NAME,
            bodyName: self::RESPONSE_BODY_NAME,
            statusCodeIndex: self::RESPONSE_STATUS_INDEX,
            bodyIndex: self::RESPONSE_BODY_INDEX
        );
    }

    function tryParseCallLikeAsResponse(
        ClassMethodContext $context,
        Node $node,
        ?ResponseCall $modifierResponseCall = null
    ): ?ResponseCall {

        // Handling static call
        if ($node instanceof StaticCall) {
            return null;
        }

        // Handling MethodCall
        if ($node instanceof MethodCall) {
            return $this->handleMethodCall($context, $node, $modifierResponseCall);
        }

        return null;
    }

    private function handleMethodCall(
        ClassMethodContext $context,
        MethodCall $node, 
        ?ResponseCall $modifierResponseCall,
    ): ?ResponseCall {
        if ($node->name instanceof Identifier) {
            $methodName = $node->name->name;
            $args = $node->args;

            $modifierResponseCall = $modifierResponseCall ?? new ResponseCall(
                type: 'application/json',
                structure: null,
                statusCode: null
            );


            switch ($methodName) {
                case self::RESPONSE_FUNCTION_SET_STATUS_CODE:
                    if (isset($args[0]) && $args[0]->value instanceof Int_) {
                        $modifierResponseCall->statusCode = $args[0]->value->value;
                    }
                    return $modifierResponseCall;

                case self::RESPONSE_FUNCTION_SET_CONTENT:
                case self::RESPONSE_FUNCTION_SET_DATA:
                    if (isset($args[0])) {
                        $modifierResponseCall->structure = $this->typeStructureResolver->resolveFromExpression(
                            context: $context,
                            expr: $args[0]->value
                        );
                    }
                    return $modifierResponseCall;


                case self::RESPONSE_FUNCTION_SEND:
                    $modifierResponseCall->hasBeenSent = true;
                    return $modifierResponseCall;
            }
        }

        return null;
    }
}