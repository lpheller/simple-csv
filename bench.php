<?php

/*
|--------------------------------------------------------------------------
| Benchmark
|--------------------------------------------------------------------------
|
| Run with "composer bench". Not part of the test suite: wall-clock numbers
| are too noisy on CI runners to assert on. The test suite guards against
| the regressions this catches by counting work instead of timing it.
|
| Pass a row count to change the fixture size: composer bench -- 5000000
|
*/

require __DIR__.'/vendor/autoload.php';

use Heller\SimpleCsv\Csv;

$rows = (int) ($argv[1] ?? 1_000_000);
$file = sys_get_temp_dir().'/simple-csv-bench.csv';

$handle = fopen($file, 'w');
fwrite($handle, "Foo,Bar,Baz\n");
for ($i = 0; $i < $rows - 1; $i++) {
    fwrite($handle, "Foo$i,Bar$i,Baz$i\n");
}
fclose($handle);

printf("%s rows, %.0f MB, PHP %s\n\n", number_format($rows), filesize($file) / 1048576, PHP_VERSION);

$cases = [
    'each()' => fn () => Csv::read($file)->each(fn ($row) => null),
    'each() + mapToHeaders()' => fn () => Csv::read($file)->mapToHeaders()->each(fn ($row) => null),
    'each() + mapToObject()' => fn () => Csv::read($file)->mapToObject()->each(fn ($row) => null),
    'each() + skipColumns()' => fn () => Csv::read($file)->mapToHeaders()->skipColumns([2])->each(fn ($row) => null),
    'each() + filter()' => fn () => Csv::read($file)->mapToHeaders()->filter(fn ($row) => $row['Foo'] === 'Foo1')->each(fn ($row) => null),
    'count()' => fn () => Csv::read($file)->count(),
    'toArray()' => fn () => Csv::read($file)->mapToHeaders()->toArray(),
];

foreach ($cases as $label => $case) {
    gc_collect_cycles();
    $start = microtime(true);
    $case();
    printf("  %-26s %6.2fs   %4.0f MB peak\n", $label, microtime(true) - $start, memory_get_peak_usage(true) / 1048576);
}

unlink($file);
