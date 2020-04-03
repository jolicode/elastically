<?php

namespace JoliCode\Elastically\Messenger;

use Elastica\Document;

interface DocumentExchangerInterface
{
    public function fetchDocument(string $className, string $id): ?Document;
}
