<?php

use Heller\SimpleCsv\Csv;

test('It writes an array to csv', function () {
    $data = [[
        'Foo',
        'Bar',
        'Baz',
    ]];

    $file = Csv::make($data)
        ->toFile(__DIR__.'/../Fixtures/data_write.csv')
        ->write();

    expect(file_get_contents(__DIR__.'/../Fixtures/data_write.csv'))
        ->toBe(
            'Foo,Bar,Baz'.PHP_EOL
        );
});
test('It writes headers to a file', function () {
    $data = [[
        'Foo',
        'Bar',
        'Baz',
    ],
    ];

    $file = Csv::make($data)
        ->withHeaders(['Col1', 'Col2', 'Col3'])
        ->toFile(__DIR__.'/../Fixtures/data_write.csv')
        ->write();

    $foo = file_get_contents(__DIR__.'/../Fixtures/data_write.csv');

    expect($foo)
        ->toBe(
            'Col1,Col2,Col3'.PHP_EOL.
            'Foo,Bar,Baz'.PHP_EOL
        );
});

test('It writes assoc array to the matching header columns', function () {
    $data = [
        [
            'Col2' => 'Bar',
            'Col1' => 'Foo',
            'Col3' => 'Baz',
        ],
    ];

    $file = Csv::make($data)
        ->withHeaders(['Col1', 'Col2', 'Col3'])
        ->toFile(__DIR__.'/../Fixtures/data_write.csv');

    $foo = file_get_contents(__DIR__.'/../Fixtures/data_write.csv');

    expect($foo)
        ->toBe(
            'Col1,Col2,Col3'.PHP_EOL.
            'Foo,Bar,Baz'.PHP_EOL
        );
});

test('It appends data to a file', function () {
    $data = [[
        'Foo',
        'Bar',
        'Baz',
    ],
    ];

    $file = Csv::make($data)
        ->withHeaders(['Col1', 'Col2', 'Col3'])
        ->toFile(__DIR__.'/../Fixtures/data_write.csv')
        ->write();

    expect(file_get_contents(__DIR__.'/../Fixtures/data_write.csv'))
        ->toBe(
            'Col1,Col2,Col3'.PHP_EOL.
            'Foo,Bar,Baz'.PHP_EOL
        );
    $data = [[
        'Foo',
        'Bar',
        'Baz',
    ]];
    $file = Csv::make($data)
        ->toFile(__DIR__.'/../Fixtures/data_write.csv')
        ->append();

    expect(file_get_contents(__DIR__.'/../Fixtures/data_write.csv'))
        ->toBe(
            'Col1,Col2,Col3'.PHP_EOL.
            'Foo,Bar,Baz'.PHP_EOL.
            'Foo,Bar,Baz'.PHP_EOL
        );
});

test('It appends assoc data to a file in the right order', function () {
    $data = [
        [
            'Col2' => 'Bar',
            'Col1' => 'Foo',
            'Col3' => 'Baz',
        ],
    ];

    unlink(__DIR__.'/../Fixtures/data_write.csv');

    $file = Csv::make($data)
        ->withHeaders(['Col1', 'Col2', 'Col3'])
        ->toFile(__DIR__.'/../Fixtures/data_write.csv')
        ->write();

    expect(file_get_contents(__DIR__.'/../Fixtures/data_write.csv'))
        ->toBe(
            'Col1,Col2,Col3'.PHP_EOL.
            'Foo,Bar,Baz'.PHP_EOL
        );
    $data = [
        [
            'Col2' => 'Bar2',
            'Col1' => 'Foo2',
            'Col3' => 'Baz2',
        ],
    ];
    $file = Csv::make($data)
        ->withHeaders(['Col1', 'Col2', 'Col3'])
        ->toFile(__DIR__.'/../Fixtures/data_write.csv')
        ->append();

    expect(file_get_contents(__DIR__.'/../Fixtures/data_write.csv'))
        ->toBe(
            'Col1,Col2,Col3'.PHP_EOL.
            'Foo,Bar,Baz'.PHP_EOL.
            'Foo2,Bar2,Baz2'.PHP_EOL
        );
});
