<?php

use Heller\SimpleCsv\Csv;

beforeEach(function () {
    $this->file = tempnam(sys_get_temp_dir(), 'simple-csv-write').'.csv';
});

afterEach(function () {
    if (is_file($this->file)) {
        unlink($this->file);
    }
});

test('It writes plain rows', function () {

    Csv::make([['Foo', 'Bar'], ['Foo1', 'Bar1']])
        ->toFile($this->file)
        ->write();

    expect(file_get_contents($this->file))->toBe("Foo,Bar\nFoo1,Bar1\n");
});

test('It writes the headers as the first row', function () {

    Csv::make([['Foo', 'Bar']])
        ->withHeaders(['Col1', 'Col2'])
        ->toFile($this->file)
        ->write();

    expect(file_get_contents($this->file))->toBe("Col1,Col2\nFoo,Bar\n");
});

test('It puts associative rows into header order', function () {

    Csv::make([['Col2' => 'Bar', 'Col1' => 'Foo']])
        ->withHeaders(['Col1', 'Col2'])
        ->toFile($this->file)
        ->write();

    expect(file_get_contents($this->file))->toBe("Col1,Col2\nFoo,Bar\n");
});

test('It writes a missing column as empty, not as a placeholder', function () {

    Csv::make([['Col1' => 'A', 'Col3' => 'C']])
        ->withHeaders(['Col1', 'Col2', 'Col3'])
        ->toFile($this->file)
        ->write();

    expect(file_get_contents($this->file))->toBe("Col1,Col2,Col3\nA,,C\n");
});

test('It writes objects by their public properties', function () {

    $row = new stdClass;
    $row->Col1 = 'Foo';
    $row->Col2 = 'Bar';

    Csv::make([$row])
        ->withHeaders(['Col1', 'Col2'])
        ->toFile($this->file)
        ->write();

    expect(file_get_contents($this->file))->toBe("Col1,Col2\nFoo,Bar\n");
});

test('It replaces the file contents on write', function () {

    file_put_contents($this->file, "old,data\nthat,goes\n");

    Csv::make([['Foo', 'Bar']])->toFile($this->file)->write();

    expect(file_get_contents($this->file))->toBe("Foo,Bar\n");
});

test('It appends without repeating the header row', function () {

    Csv::make([['Foo', 'Bar']])
        ->withHeaders(['Col1', 'Col2'])
        ->toFile($this->file)
        ->write();

    Csv::make([['Foo1', 'Bar1']])
        ->toFile($this->file)
        ->append();

    expect(file_get_contents($this->file))->toBe("Col1,Col2\nFoo,Bar\nFoo1,Bar1\n");
});

test('It appends associative rows in the order of the header already in the file', function () {

    file_put_contents($this->file, "Col1,Col2,Col3\n");

    Csv::make([['Col3' => 'C', 'Col1' => 'A']])
        ->toFile($this->file)
        ->append();

    expect(file_get_contents($this->file))->toBe("Col1,Col2,Col3\nA,,C\n");
});

test('It writes the header when appending to an empty file', function () {

    Csv::make([['Foo', 'Bar']])
        ->withHeaders(['Col1', 'Col2'])
        ->toFile($this->file)
        ->append();

    expect(file_get_contents($this->file))->toBe("Col1,Col2\nFoo,Bar\n");
});

test('It writes with a custom delimiter', function () {

    Csv::make([['Foo', 'Bar']])
        ->withHeaders(['Col1', 'Col2'])
        ->delimiter(';')
        ->toFile($this->file)
        ->write();

    expect(file_get_contents($this->file))->toBe("Col1;Col2\nFoo;Bar\n");
});

test('It writes CRLF line endings for Excel', function () {

    Csv::make([['Foo', 'Bar']])
        ->withHeaders(['Col1', 'Col2'])
        ->crlf()
        ->toFile($this->file)
        ->write();

    expect(file_get_contents($this->file))->toBe("Col1,Col2\r\nFoo,Bar\r\n");
});

test('It keeps the line ending when appending', function () {

    Csv::make([['Foo', 'Bar']])
        ->withHeaders(['Col1', 'Col2'])
        ->crlf()
        ->toFile($this->file)
        ->write();

    Csv::make([['Foo1', 'Bar1']])
        ->crlf()
        ->toFile($this->file)
        ->append();

    expect(file_get_contents($this->file))->toBe("Col1,Col2\r\nFoo,Bar\r\nFoo1,Bar1\r\n");
});

test('It still reads back what it wrote with CRLF', function () {

    $rows = [['name' => 'Ada', 'city' => 'Berlin']];

    Csv::make($rows)->withHeaders(['name', 'city'])->crlf()->toFile($this->file)->write();

    expect(Csv::read($this->file)->mapToHeaders()->toArray())->toBe($rows);
});

test('It throws when no target file was set', function () {

    expect(fn () => Csv::make([['Foo']])->write())
        ->toThrow(RuntimeException::class, 'No target file set, call toFile() before writing.');
});

test('It throws when the target directory does not exist', function () {

    expect(fn () => Csv::make([['Foo']])->toFile('/no/such/dir/out.csv')->write())
        ->toThrow(RuntimeException::class, 'Cannot write to directory: /no/such/dir');
});

test('What it writes can be read back', function () {

    $rows = [
        ['name' => 'Ada', 'city' => 'Berlin'],
        ['name' => 'Bob', 'city' => 'Hamburg'],
    ];

    Csv::make($rows)
        ->withHeaders(['name', 'city'])
        ->toFile($this->file)
        ->write();

    expect(Csv::read($this->file)->mapToHeaders()->toArray())->toBe($rows);
});
