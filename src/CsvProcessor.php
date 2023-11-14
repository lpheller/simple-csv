<?php

namespace Heller\SimpleCsv;

use Closure;
use Generator;
use Heller\SimpleCsv\Support\FileHandler;

class CsvProcessor
{
    public $shouldMapToHeaders = false;

    public int $headerRow = 1;

    public array $skipRows = [];

    public array $skipColumns = [];

    public ?Closure $filterCallback = null;

    public bool $mapToObject = false;

    public ?string $customObjectClass = null;

    public string $delimiter = ',';

    public bool $skipEmptyRows = false;

    public array $headers = [];

    public function __construct(public FileHandler $fileHandler)
    {
    }

    public function process(): Generator
    {
        if (($handle = $this->fileHandler->openFile()) === false) {
            return;
        }

        $rowNumber = 0;

        while (($row = fgetcsv($handle, null, $this->delimiter)) !== false) {
            $rowNumber++;

            if (in_array($rowNumber, $this->skipRows)) {
                continue; // Skip rows based on specified row numbers
            }

            if ($this->skipEmptyRows && empty(array_filter($row))) {
                continue; // Skip empty rows
            }

            $row = $this->prepareRow($row);

            if ($this->filterCallback instanceof \Closure && ! call_user_func($this->filterCallback, $row)) {
                continue; // Skip rows that don't match the filter criteria
            }

            yield $row;

        }

        fclose($handle);
    }

    /**
     * Precessing a single row of CSV data
     */
    public function prepareRow(array $row): array|object
    {
        $row = $this->skipColumnsByIndex($row);
        $row = $this->skipColumnsByHeaderName($row);

        if (! $this->shouldMapToHeaders) {
            return $row;
        }

        // Read CSV file and handle header row
        $header = $this->getHeaderRow();

        $header = $this->skipColumnsByIndex($header);
        $header = $this->skipColumnsByHeaderName($header);

        if (! $header) {
            return $row;
        }

        $header = $this->mapToObject ? $this->normalizeHeaders($header) : $header;

        $row = $this->combineWithHeader(
            $header,
            $row
        );

        if (! $this->mapToObject) {
            return $row;
        }

        // Create an object instance based on user preference (stdClass or custom class)
        return $this->createObjectInstance($row);
    }

    /**
     * Get the header row from the CSV file.
     *
     * If headers were set manually, return those.
     * Otherwise read the header row from the CSV based on headerRow index.
     */
    public function getHeaderRow()
    {
        if ($this->headers) {
            return $this->headers;
        }

        return $this->getHeaderRowFromCsv();
    }

    protected function getHeaderRowFromCsv()
    {
        $handle = $this->fileHandler->openFile();

        // Skip rows until the header row
        for ($i = 1; $i < $this->headerRow; $i++) {
            if (fgetcsv($handle) === false) {
                throw new \RuntimeException('Header row not found in CSV.');
            }
        }

        $header = fgetcsv(
            $handle,
            null,
            $this->delimiter
        );
        fclose($handle);

        return $header;
    }

    public function createObjectInstance(array $row)
    {
        // Check if custom object class is specified and exists
        if (! $this->customObjectClass || ! class_exists($this->customObjectClass)) {
            return (object) $row;
        }

        $object = new $this->customObjectClass();

        foreach ($row as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }

        return $object; // Early return for custom class mapping

    }

    public function normalizeHeaders($headers)
    {
        return array_map(
            function ($header) {
                $header = str_replace(' ', '_', $header);
                $header = str_replace(['(', ')'], '', $header);
                $header = preg_replace('/[^a-zA-Z0-9_]/', '_', $header);

                return strtolower($header);
            },
            $headers
        );
    }

    public function skipColumnsByHeaderName($row)
    {
        if ($this->skipColumns === []) {
            return $row;
        }
        $header = $this->getHeaderRow();

        foreach ($this->skipColumns as $skipColumn) {
            if (is_string($skipColumn) && in_array($skipColumn, $header)) {
                $index = array_search($skipColumn, $header);
                unset($row[$index]);
            }
        }

        return array_values($row);
    }

    public function skipColumnsByIndex($row)
    {
        if ($this->skipColumns === []) {
            return $row;
        }

        foreach ($this->skipColumns as $skipColumn) {

            if (is_numeric($skipColumn)) {
                unset($row[$skipColumn - 1]); // Skip by column index
            }
        }

        return array_values($row); // Re-index the row array
    }

    public function combineWithHeader($header, $row)
    {
        if (count($row) === count($header)) {
            return array_combine($header, $row);
        }

        return $row;
    }

    /**
     * Insert a row with data at the specified index in the CSV file.
     *
     * @param  int  $position The index to insert the row at
     * @param  array  $rowData The data for the new row
     */
    public function insertAt($position = 0, $rowData = [])
    {
        // if rows are skipped, we need to add the number of skipped rows to the position
        // this is mainly when the rows are mapped to the headers
        if ($this->skipRows != []) {
            $position = $position + count($this->skipRows);
        }

        $writer = new CsvWriter([]);
        $writer->setFileHandler($this->fileHandler);
        $writer->insertRow($position, $rowData);
    }

    /**
     * Appending data to the end of the CSV file.
     */
    public function append($rowData = [])
    {
        $writer = new CsvWriter($rowData);
        $writer->setFileHandler($this->fileHandler);
        $writer->write(
            append: true
        );
    }
}
