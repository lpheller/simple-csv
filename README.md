# Simple CSV

Make dealing with CSV data as easy and comfortable as possible.

Reads local files, URLs and Google Spreadsheets through one fluent API. Every
read is generator based, so a file of any size costs the same memory as a single
row — unless you explicitly ask for the whole thing with `toArray()`.

## Requirements

PHP 8.3 or higher.

## Installation

```sh
composer require heller/simple-csv
```

## Reading

```php
use Heller\SimpleCsv\Csv;

Csv::read('data.csv')->toArray();
Csv::read('https://example.com/data.csv')->toArray();

// Spreadsheet URLs are rewritten to their CSV export automatically
Csv::read('https://docs.google.com/spreadsheets/d/ABC123/edit')->toArray();
```

An unreadable path throws a `RuntimeException` rather than returning an empty
result, so a typo in a filename cannot look like an empty import.

### Delimiter

```php
Csv::read('data.csv')->delimiter(';')->toArray();
```

## Header mapping

`mapToHeaders()` uses a row of the CSV as the keys for every data row. The
header row itself is never returned as data.

```php
$rows = Csv::read('data.csv')->mapToHeaders()->toArray();

foreach ($rows as $row) {
    echo $row['columnname']; // instead of $row[3]
}
```

Pass a row number if the header is not the first row:

```php
Csv::read('data.csv')->mapToHeaders(3)->toArray();
```

Pass an array to supply your own header names. No row is consumed, so every
line in the file is treated as data:

```php
Csv::read('data.csv')->mapToHeaders(['id', 'name', 'email'])->toArray();
```

Read the header without reading the file:

```php
Csv::read('data.csv')->getHeaderRow(); // ['Foo', 'Bar', 'Baz']
```

`getHeaderRow()` returns the header as it appears in the file — `skipColumns()`
is not applied to it.

### Ragged rows

Rows with a different column count than the header keep their header keys.
Missing values become `null`, surplus values keep their column index:

```php
// id,name,mail
// 1,Ada
// 2,Bob,b@x.de,extra

['id' => '1', 'name' => 'Ada', 'mail' => null]
['id' => '2', 'name' => 'Bob', 'mail' => 'b@x.de', 3 => 'extra']
```

A UTF-8 BOM — written by Excel and Google Sheets — is stripped, so the first
header name is usable as a key.

## Mapping to objects

By default each row becomes a `stdClass`, so you can use property access:

```php
Csv::read('data.csv')->mapToObject()->toArray();
```

Pass a class name to map onto your own type. Values are assigned to properties
whose names match the column, other columns are ignored:

```php
Csv::read('data.csv')
    ->mapToObject(CsvRow::class)
    ->filter(fn (CsvRow $row) => $row->isValid())
    ->toArray();
```

Column names are normalized to valid property names when mapping to objects: a
column `Starts At (UTC)` becomes `$row->starts_at_utc`. Note the difference to
`mapToHeaders()`, which keeps the original names as array keys.

`mapToObject()` implies `mapToHeaders()`, you do not need to call both.

## Skipping

Rows and columns are numbered from 1. Both methods take a single value or an
array, and `skipColumns()` also accepts column names.

```php
Csv::read('data.csv')
    ->skipRows(1)
    ->skipColumns([2, 4, 'columnname'])
    ->toArray();
```

`skipRows()` is independent of `mapToHeaders()` — the header row is skipped in
addition to whatever you list, in any call order.

Rows where every column is empty are returned by default. Drop them with:

```php
Csv::read('data.csv')->skipEmptyRows()->toArray();
```

## Filtering

The callback receives the row after mapping, so it gets an array or an object
depending on what you configured. Filtering happens while reading, which keeps
it cheap on large files.

```php
Csv::read('data.csv')
    ->mapToHeaders()
    ->filter(fn ($row) => $row['column'] !== 'foo')
    ->toArray();
```

## Getting the data out

```php
$csv = Csv::read('data.csv')->mapToHeaders();

$csv->toArray(); // array of all rows
$csv->toJson();  // JSON string of all rows
$csv->first();   // first row, or null if there is none
$csv->count();   // number of rows, with filter and skips applied
```

`toArray()` and `toJson()` hold the entire file in memory. For anything large,
process row by row instead — this is memory constant and works on files with
millions of records:

```php
Csv::read('data.csv')
    ->mapToObject(CsvRow::class)
    ->each(function (CsvRow $row) {
        // import or handle the row however you like
    });
```

## Writing

```php
Csv::make($rows)->toFile('out.csv')->write();
```

`write()` replaces the file. Pass header names to get them as the first row:

```php
Csv::make([['Ada', 'Berlin']])
    ->withHeaders(['name', 'city'])
    ->toFile('out.csv')
    ->write();

// name,city
// Ada,Berlin
```

Associative rows are put into header order regardless of the order of their
keys, and a column a row does not carry is written empty:

```php
Csv::make([['city' => 'Berlin', 'name' => 'Ada'], ['name' => 'Bob']])
    ->withHeaders(['name', 'city'])
    ->toFile('out.csv')
    ->write();

// name,city
// Ada,Berlin
// Bob,
```

Objects are written by their public properties, so anything read with
`mapToObject()` can be written straight back out.

### Appending

`append()` keeps the existing contents and does not repeat the header row. When
no headers are set, the header already in the file defines the column order:

```php
Csv::make([['city' => 'Hamburg', 'name' => 'Bob']])
    ->toFile('out.csv')
    ->append();
```

If the file is missing or empty, `append()` writes it like `write()` would,
header included.

### Inserting at a position

`insertAt()` puts rows in front of an existing record. Records are counted from
1 and the header is record 1, so the first data row is position 2:

```php
// Col1,Col2
// A,A
// B,B

Csv::make([['NEW', 'NEW']])->toFile('data.csv')->insertAt(3);

// Col1,Col2
// A,A
// NEW,NEW
// B,B
```

Everything after the insert is copied byte for byte, so quoting and spacing of
untouched records survive. The file is rebuilt next to itself and moved into
place in one step, which means a crash mid-write cannot leave a half-written
file behind. Memory stays constant regardless of file size — inserting into a
1M row file costs about 2 MB.

A position past the end appends. A missing or empty file is written from
scratch, like `write()`.

### Delimiter and line endings

```php
Csv::make($rows)->delimiter(';')->toFile('out.csv')->write();
```

Rows end with `\n`. Excel on Windows expects `\r\n`:

```php
Csv::make($rows)->crlf()->toFile('out.csv')->write();
```

## Known limitations

- Backslash still acts as an escape character inside quoted fields, matching
  PHP's current `fgetcsv` default. A field ending in `\` can swallow its
  closing quote.
- No encoding conversion. Input is expected to be UTF-8, and no BOM is written.
- Existing records can be inserted in front of, but not changed or removed.

## Development

```sh
composer test   # pest
composer lint   # pint
composer bench  # 1M row benchmark, generates its own fixture
```

## License

MIT. See [LICENSE](LICENSE).
