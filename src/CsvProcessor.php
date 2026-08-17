<?php

namespace Heller\SimpleCsv;

use Closure;
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

    /**
     * RFC 4180 has no escape character, a quote is doubled instead. PHP's
     * historic default is a backslash, which makes a field ending in one
     * swallow its closing quote.
     */
    public string $escape = '';

    public bool $skipEmptyRows = false;

    public array $headers = [];

    private ?array $cachedHeaderRow = null;

    private ?array $preparedHeaderRow = null;

    public function __construct(public FileHandler $fileHandler) {}

    public function process()
    {
        $handle = $this->fileHandler->openFile();

        $rowNumber = 0;

        while (($row = fgetcsv($handle, null, $this->delimiter, escape: $this->escape)) !== false) {
            $rowNumber++;

            if (in_array($rowNumber, $this->skipRows)) {
                continue; // Skip rows based on specified row numbers
            }

            if ($this->isHeaderRow($rowNumber)) {
                continue; // The header row is not data
            }

            if ($this->skipEmptyRows && empty(array_filter($row))) {
                continue; // Skip empty rows
            }

            $row = $this->prepareRow($row);

            if ($this->filterCallback instanceof Closure && ! call_user_func($this->filterCallback, $row)) {
                continue; // Skip rows that don't match the filter criteria
            }

            yield $row;

        }

        fclose($handle);
    }

    /**
     * Precessing a single row of CSV data
     *
     * @return array|object
     */
    public function prepareRow(array $row)
    {
        $row = $this->skipColumnsByIndex($row);
        $row = $this->skipColumnsByHeaderName($row);

        if (! $this->shouldMapToHeaders) {
            return $row;
        }

        $header = $this->preparedHeaderRow();

        if (! $header) {
            return $row;
        }

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
     * Only rows read from the CSV are skipped. Headers passed in via
     * setHeaders() do not occupy a row.
     */
    protected function isHeaderRow(int $rowNumber): bool
    {
        return $this->shouldMapToHeaders
            && $this->headers === []
            && $rowNumber === $this->headerRow;
    }

    public function getHeaderRow()
    {
        if ($this->headers) {
            return $this->headers;
        }

        return $this->cachedHeaderRow ??= $this->getHeaderRowFromCsv();
    }

    /**
     * The header row with skipped columns removed and, when mapping to
     * objects, normalized names. Identical for every data row.
     */
    protected function preparedHeaderRow(): array
    {
        if ($this->preparedHeaderRow !== null) {
            return $this->preparedHeaderRow;
        }

        $header = $this->getHeaderRow();

        if (! $header) {
            return $this->preparedHeaderRow = [];
        }

        $header = $this->skipColumnsByIndex($header);
        $header = $this->skipColumnsByHeaderName($header);

        return $this->preparedHeaderRow = $this->mapToObject ? $this->normalizeHeaders($header) : $header;
    }

    protected function getHeaderRowFromCsv()
    {
        $handle = $this->fileHandler->openFile();

        // Skip rows until the header row
        for ($i = 1; $i < $this->headerRow; $i++) {
            if (fgetcsv($handle, escape: $this->escape) === false) {
                throw new \RuntimeException('Header row not found in CSV.');
            }
        }

        $header = fgetcsv(
            $handle,
            null,
            $this->delimiter,
            escape: $this->escape
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

        $object = new $this->customObjectClass;

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

    /**
     * Ragged rows keep the header keys: missing columns become null, surplus
     * values keep their column index. Returning the raw positional row here
     * would hand back two different shapes from the same file.
     */
    public function combineWithHeader($header, $row)
    {
        $columns = count($header);

        if (count($row) === $columns) {
            return array_combine($header, $row);
        }

        $combined = array_combine(
            $header,
            array_pad(array_slice($row, 0, $columns), $columns, null)
        );

        return $combined + array_slice($row, $columns, null, true);
    }
}
