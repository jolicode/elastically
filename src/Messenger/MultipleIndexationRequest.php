<?php

namespace JoliCode\Elastically\Messenger;

final class MultipleIndexationRequest implements IndexationRequestInterface
{
    private $operations = [];

    public function __construct(array $operations)
    {
        $this->operations = $operations;
    }

    public function getOperations(): array
    {
        return $this->operations;
    }
}
