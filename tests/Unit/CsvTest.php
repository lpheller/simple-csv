<?php

use Heller\SimpleCsv\Csv;

test('It returns each data row as an array', function () {

    $file = __DIR__.'/../Fixtures/data.csv';

    $csv = Csv::read($file);

    expect($csv->toArray())->toBe([
        [
            'Foo', 'Bar', 'Baz',
        ],
        [
            'Foo1', 'Bar1', 'Baz1',
        ],
    ]);
});

test('It returns each data row as array with special delimiter', function () {

    $file = __DIR__.'/../Fixtures/data_delimiter.csv';

    $csv = Csv::read($file)
        ->delimiter(';');

    expect($csv->toArray())->toBe([
        [
            'Foo', 'Bar', 'Baz',
        ],
        [
            'Foo1', 'Bar1', 'Baz1',
        ],
    ]);
});

test('It maps the row arrays to the header keys', function () {

    $file = __DIR__.'/../Fixtures/data.csv';

    $csv = Csv::read($file)
        ->mapToHeaders();

    expect($csv->toArray())->toBe([
        [
            'Foo' => 'Foo1',
            'Bar' => 'Bar1',
            'Baz' => 'Baz1',
        ],
    ]);
});

test('It maps the row arrays to the header keys and skips the header row', function () {

    $file = __DIR__.'/../Fixtures/data_headers.csv';

    $csv = Csv::read($file)
        ->mapToHeaders(2);

    expect($csv->getHeaderRow())->toBe([
        'Foo',
        'Bar',
        'Baz',
    ]);

    expect($csv->toArray())->toBe([
        [
            'Foo' => 'Bla',
            'Bar' => 'Bla',
            'Baz' => 'Bla',
        ],
        [
            'Foo' => 'Bla1',
            'Bar' => 'Bla1',
            'Baz' => 'Bla1',
        ],
        [
            'Foo' => 'Foo1',
            'Bar' => 'Bar1',
            'Baz' => 'Baz1',
        ],
    ]);
});

test('It skips a certain amount of rows', function () {

    $file = __DIR__.'/../Fixtures/data.csv';

    $csv = Csv::read($file)
        ->skipRows(1);

    expect($csv->toArray())->toBe([
        [
            'Foo1', 'Bar1', 'Baz1',
        ],
    ]);
});

test('It skips a certain amount of columns', function () {

    $file = __DIR__.'/../Fixtures/data.csv';

    $csv = Csv::read($file)
        ->skipColumns(1);

    expect($csv->toArray())->toBe([
        ['Bar', 'Baz'],
        ['Bar1', 'Baz1'],
    ]);
});

test('It skips columns by column Name', function () {

    $file = __DIR__.'/../Fixtures/data.csv';

    $csv = Csv::read($file)
        ->skipColumns(['Foo']);

    expect($csv->toArray())->toBe([
        ['Bar', 'Baz'],
        ['Bar1', 'Baz1'],
    ]);
});

test('It skips columns by column name and index', function () {

    $file = __DIR__.'/../Fixtures/data.csv';

    $csv = Csv::read($file)
        ->skipColumns(['Foo', 1]);

    expect($csv->toArray())->toBe([
        ['Baz'],
        ['Baz1'],
    ]);
});

test('It returns the header row', function () {

    $file = __DIR__.'/../Fixtures/data.csv';

    $csv = Csv::read($file);

    expect($csv->getHeaderRow())->toBe([
        'Foo', 'Bar', 'Baz',
    ]);
});

test('It works with a csv from an url', function () {

    // $file = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQbCA0PYQtwEDF2g4rv3-22vUpoBaNaYWFNW3wR0s0a904D-9vRfmIkNzA7VmKDArfGY81whg9tWhWp/pub?gid=0&single=true&output=csv';
    $file = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQbCA0PYQtwEDF2g4rv3-22vUpoBaNaYWFNW3wR0s0a904D-9vRfmIkNzA7VmKDArfGY81whg9tWhWp/pub?gid=103922319&single=true&output=csv';

    // $file = __DIR__.'/../Fixtures/data_10krows.csv';
    $csv = Csv::read($file)->mapToHeaders();

    expect(count($csv->toArray()))->toBeGreaterThan(0);
    // ray()->measure();

});

test('It works with a plain google spreadhset url', function () {
    $url = 'https://docs.google.com/spreadsheets/d/1F4yuxvNYcBD_91MFQdgWqxXPQL1HC3PP_JOnN1Mizr0';

    $csv = Csv::read($url)->toArray();

    expect($csv[0])->toEqual(
        [
            'Foo', 'Bar', 'Baz',
        ],
    );
});

test('it filters the data', function () {

    $file = __DIR__.'/../Fixtures/data_10krows.csv';
    // $file = __DIR__.'/../Fixtures/data.csv';

    $csv = Csv::read($file)
        ->mapToHeaders()
        ->filter(function ($row) {
            return $row['Foo'] === 'Foo1';
        });

    expect($csv->toArray())->toBe([
        [
            'Foo' => 'Foo1',
            'Bar' => 'Bar1',
            'Baz' => 'Baz1',
        ],
    ]);
});

test('it counts the rows', function () {

    $file = __DIR__.'/../Fixtures/data_10krows.csv';

    $csv = Csv::read($file);
    expect($csv->count())->toBe(10000);

    $csv = Csv::read($file)->mapToHeaders();
    expect($csv->count())->toBe(9999);

    $csv = Csv::read($file)
        ->mapToHeaders()
        ->filter(function ($row) {
            return $row['Foo'] === 'Foo1';
        });
    expect($csv->count())->toBe(1);
});

test('it maps the rows to stdClass objects', function () {

    $file = __DIR__.'/../Fixtures/data_10krows.csv';

    $csv = Csv::read($file)
        ->mapToHeaders()
        ->mapToObject();

    expect($csv->toArray()[0])->toBeInstanceOf(stdClass::class);
    expect($csv->toArray()[0])->toEqual((object) [
        'foo' => 'Foo1',
        'bar' => 'Bar1',
        'baz' => 'Baz1',
    ]);

});

test('it maps the rows to custom objet classes', function () {

    $file = __DIR__.'/../Fixtures/data.csv';

    class TestRow
    {
        public $Foo;

        public $Baz;
    }

    $csv = Csv::read($file)
        ->mapToHeaders()
        ->mapToObject(TestRow::class);

    expect($csv->toArray())->each(function ($row) {
        expect($row->value)->toBeInstanceOf(TestRow::class);
    });

});

test('if mapped to objet the filter receives an object', function () {

    $file = __DIR__.'/../Fixtures/data.csv';

    $csv = Csv::read($file)
        ->mapToHeaders()
        ->filter(function ($row) {
            expect($row)->toBeInstanceOf(stdClass::class);

            return $row;
        })
        ->mapToObject();

    expect($csv->toArray())->toEqual([
        (object) [
            'foo' => 'Foo1',
            'bar' => 'Bar1',
            'baz' => 'Baz1',
        ],
    ]);
});

test('it normalizes the header names when mapping to object', function () {

    $file = __DIR__.'/../Fixtures/data_bad_headers.csv';

    $csv = Csv::read($file)
        ->mapToHeaders()
        ->mapToObject();

    expect($csv->toArray()[0])->toBeInstanceOf(stdClass::class);
    expect($csv->toArray()[0])->toEqual((object) [
        'foo' => 'Foo1',
        'bar_ito' => 'Bar1',
        'baz' => 'Baz1',
    ]);
});

test('it processes large files', function () {
    // works at least up to 10m rows
    $file = makeTestFile(100000);

    $csv = Csv::read($file);

    $i = 0;

    $csv->each(function ($row) use (&$i) {
        $i++;
    });

    expect($i)->toBe(100000);

    unlink($file);
});

function makeTestFile($rows = 1000)
{

    $path = __DIR__.'/../Fixtures/testfile.csv';
    $fp = fopen($path, 'w'); // open in write only mode (write at the start of the file)

    $row = [
        'Foo' => 'Foo',
        'Bar' => 'Bar',
        'Baz' => 'Baz',
    ];
    fputcsv($fp, $row);

    for ($i = 0; $i < ($rows - 1); $i++) {
        $row = [
            'Foo' => 'Foo'.$i,
            'Bar' => 'Bar'.$i,
            'Baz' => 'Baz'.$i,
        ];
        fputcsv($fp, $row);
    }
    fclose($fp);

    return $path;
}
test('It returns json data', function () {
    $file = makeTestFile(2);

    $csv = Csv::read($file)
        ->mapToHeaders();

    expect($csv->toJson())->toBe('[{"Foo":"Foo0","Bar":"Bar0","Baz":"Baz0"}]');

    unlink($file);
});

test('It maps to custom headers', function () {

    $file = makeTestFile(2);

    $csv = Csv::read($file)
        ->mapToHeaders([
            'fii',
            'foo',
            'faa',
        ])
        ->toArray();

    expect($csv)->toBe([
        [
            'fii' => 'Foo',
            'foo' => 'Bar',
            'faa' => 'Baz',
        ],
        [
            'fii' => 'Foo0',
            'foo' => 'Bar0',
            'faa' => 'Baz0',
        ],
    ]);

    unlink($file);
});

test('it skips empty rows by default', function () {
    $file = __DIR__.'/../Fixtures/data_with_empty_rows.csv';
    $csv = Csv::read($file)
        ->mapToHeaders()
        ->skipEmptyRows();

    expect($csv->count())->toBe(2);

    $csv->skipEmptyRows(false);
    expect($csv->count())->toBe(3);

});
