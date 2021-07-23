<?php

return [
    'analyzer' => [
        'beer_name' => [
            'tokenizer' => 'classic',
            'filter' => ['elision'],
        ],
    ],
];
