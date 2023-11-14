# Simple CSV

Make dealing with CSV data as easy and comfortable as possible.

## Installation

```sh
composer require heller/simple-csv
```

## Usage

### Basic array

```php
use Heller\SimpleCsv\Csv;

$csv = Csv::read('filepath.csv')->toArray();

$csv = Csv::read('http://urlto.csv')->toArray();
```

### Header mapping

Assuming that you have a csv table where the first row holds the cullum names
using the `mapToHeader` function makes handling the data much easier.

```php
use Heller\SimpleCsv\Csv;

$csv = Csv::read('filepath.csv')
            ->mapToHeaders()
            ->toArray();

foreach($csv as $row){
    echo $row['columnname']; // instead of using $row[3]
}
```

### Skipping rows & columns

Sometimes it can be helpful to skip certain rows or columns.
You can do that using `skipRows` and `skipColumns` methods.

```php
$csv = Csv::read('filepath.csv')
           ->skipRows(1)
           ->skipColumns([2, 4, 'columnname'])
           ->toArray();
```

Both methods accept either an `int` for a certain row / column or `array` to skip
multiple rows or columns.
The `skipColumns` method also accepts column (header) names as input.

### Filtering

As the csv data is just array, we can of course filter the data however you like.
For convenience we can also filter while collecting the data using the filter() callback.

```php
$csv = Csv::read('filepath.csv')
           ->filter(fn($row) => $row['column'] != 'foo')
           ->toArray();
```

### Map to object

Mapping rows to objects allows you to work with CSV data in a more structured way.
By default the rows are mapped to `stdClass` which allows to access the CSV data using object properties.

However, if you specify a custom class each row will be mapped to the class based on the header column names.

```php
// ...

$csv->mapToObject(); // Maps rows to stdClass

$csv->mapToObject(CsvRow::class)
    ->filter(function(CsvRow $item){
        return $item->isValid();
    })
    ->toArray();

```

When using the mapToObject method, the column headers slightly adjusted so
the object properties are ensured to have valid names.
If a column header was named "Starts At", the name will be normalized to "starts_at"
so it can be accessed with `$row->starts_at`.
For custom class mapping the (normalized) column header has to match the class property name.

### Individually process row

The `get` method will allways return an of rows from the csv file. Either as arrays or object.
However, while being handy for smaller tasks, this is not memory efficient and can cause problems with large csv files.
To solve this, you can process each row individually and still let the package solve the mapping, filtering and skipping etc.
This even makes handling large files with millions of records as simple as possible.

```php
$csv->mapToObject(CsvRow::class)
    ->each(function(CsvRow $item) {
        // ... import the data or handle the data however you like
    });
```
