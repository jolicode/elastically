<?php

namespace JoliCode\Elastically\Messenger;

final class IndexationRequest
{
    private $operation;
    private $type;
    private $id;

    public function __construct(string $type, string $id, string $operation = IndexationRequestHandler::OP_INDEX)
    {
        if (!in_array($operation, IndexationRequestHandler::OPERATIONS, true)) {
            throw new \InvalidArgumentException(sprintf('Not supported operation "%s" given.', $operation));
        }

        $this->type = $type;
        $this->id = $id;
        $this->operation = $operation;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
