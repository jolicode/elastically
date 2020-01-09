<?php

namespace JoliCode\Elastically\Messenger;

final class IndexationRequest
{
    private $op;
    private $type;
    private $id;

    public function __construct(string $type, string $id, string $op = IndexationRequestHandler::OP_INDEX)
    {
        if (!in_array($op, IndexationRequestHandler::OPS, true)) {
            throw new \InvalidArgumentException(sprintf('Not supported operation "%s" given.', $op));
        }

        $this->type = $type;
        $this->id = $id;
        $this->op = $op;
    }

    public function getOp(): string
    {
        return $this->op;
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
