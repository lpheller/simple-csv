<?php

namespace Heller\SimpleCsv;

use Closure;

class Csv
{
    protected $processor;

    public function __construct(string $filePath)
    {
        $this->processor = new CsvProcessor(
            new Support\FileHandler($filePath)
        );
    }

    /**
     * Create a new instance of the Csv class.
     *
     * @param  string  $filePath The path or url to the CSV file
     * @return $this
     */
    public static function read(string $filePath)
    {
        return new self($filePath);
    }

    /**
     * Set the delimiter for the CSV file.
     *
     * @param  string  $delimiter The delimiter for the CSV file
     * @return $this
     */
    public function delimiter(string $delimiter)
    {
        $this->processor->delimiter = $delimiter;

        return $this;
    }

    /**
     * Map the CSV data to header keys.
     * The header row will be used as the keys for the data rows.
     *
     * @param  int|array  $headerRow The header row number or an array of header names
     * @return $this
     */
    public function mapToHeaders(array|int|bool $headerRow = 1)
    {
        $this->processor->shouldMapToHeaders = (bool) $headerRow;

        if (is_array($headerRow)) {
            return $this->setHeaders($headerRow);
        }

        $this->setHeaderRow($headerRow);

        // Skip the header row when processing the CSV
        $this->skipRows([$headerRow]);

        return $this;
    }

    /**
     * Set the header row number.
     * This is only used when mapping the CSV data to header keys.
     *
     * @param  int  $row The header row number
     * @return $this
     */
    public function setHeaderRow(int $row)
    {
        $this->processor->headerRow = $row;

        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->skipRows([]);
        $this->processor->headers = $headers;

        return $this;
    }

    /**
     * Get the header row as an array.
     *
     * @return array
     */
    public function getHeaderRow()
    {
        return $this->processor->getHeaderRow();
    }

    /**
     * Skip rows by index.
     *
     * @param  int|array  $rows The row numbers to skip
     * @return $this
     */
    public function skipRows(int|array $rows)
    {
        $this->processor->skipRows = is_array($rows) ? $rows : [$rows];

        return $this;
    }

    /**
     * Skip columns by index or header name.
     *
     * @param  int|array  $columns The column numbers or header names to skip
     * @return $this
     */
    public function skipColumns(int|array $columns)
    {
        $this->processor->skipColumns = is_array($columns) ? $columns : [$columns];

        return $this;
    }

    /**
     * Filter the rows with a callback function.
     *
     * @param  Closure  $callback The callback function to filter the rows
     * @return $this
     */
    public function filter(Closure $callback)
    {
        $this->processor->filterCallback = $callback;

        return $this;
    }

    /**
     * Map the rows data to an object. If a custom class is specified, the
     * object will be an instance of that class. Otherwise, it will be an
     * instance of stdClass.
     *
     * @param  string|null  $customClass The custom class to map the data to
     * @return $this
     */
    public function mapToObject(string $customClass = null)
    {
        $this->mapToHeaders();
        $this->processor->mapToObject = true;
        $this->processor->customObjectClass = $customClass;

        return $this;
    }

    /**
     * Count the number of rows with filter applied.
     *
     * @return int
     */
    public function count()
    {
        $count = 0;

        foreach ($this->processor->process() as $row) {
            $count++;
        }

        return max(0, $count); // Ensure a non-negative count
    }

    /**
     * Get the first row of the CSV data.
     *
     * @return array|object
     */
    public function first()
    {
        foreach ($this->processor->process() as $row) {
            return $row;
        }
    }

    /**
     * Get the CSV data as an array.
     * Warning: This will load the entire CSV into memory, so it shouldn't be
     * used for huge files. Use the process() method instead to process the
     * CSV line by line.
     *
     *  @return array
     */
    public function toArray()
    {
        $data = [];
        foreach ($this->processor->process() as $row) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Process the CSV data line by line.
     *
     * @param  callable  $callback The callback function to process each row
     */
    public function each($callback)
    {
        foreach ($this->processor->process() as $row) {
            $callback($row);
        }
    }

    public function skipEmptyRows($shouldSkip = true)
    {
        $this->processor->skipEmptyRows = $shouldSkip;

        return $this;
    }

    /**
     * Get the CSV data as JSON.
     * Warning: This will load the entire CSV into memory, so it shouldn't be
     * used for huge files. Use the process() method instead to process the
     * CSV line by line.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
