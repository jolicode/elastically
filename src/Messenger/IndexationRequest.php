<?php

namespace JoliCode\Elastically\Messenger;

final class IndexationRequest implements IndexationRequestInterface
{
    private $operation;
    private $className;
    private $id;

    public function __construct(string $className, string $id, string $operation = IndexationRequestHandler::OP_INDEX)
    {
        if (!in_array($operation, IndexationRequestHandler::OPERATIONS, true)) {
            throw new \InvalidArgumentException(sprintf('Not supported operation "%s" given.', $operation));
        }

        $this->className = $className;
        $this->id = $id;
        $this->operation = $operation;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
