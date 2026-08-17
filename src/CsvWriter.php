<?php

namespace Heller\SimpleCsv;

class CsvWriter
{
    protected array $headers = [];

    protected ?string $filePath = null;

    protected string $delimiter = ',';

    public function __construct(protected array $data) {}

    /**
     * Set the file to write to. The file is only touched by write() or append().
     */
    public function toFile(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    /**
     * Write these names as the first row, and use them as the column order for
     * associative rows.
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    public function delimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * Write the data, replacing whatever is in the file.
     */
    public function write(): static
    {
        $handle = $this->openTarget('w');

        $this->putRows($handle, $this->headers, $this->headers);

        fclose($handle);

        return $this;
    }

    /**
     * Append the data, keeping the existing contents and header row. Falls back
     * to a full write when the file is missing or empty.
     */
    public function append(): static
    {
        $existingHeaders = $this->headerRowInFile();

        if ($existingHeaders === null) {
            return $this->write();
        }

        $handle = $this->openTarget('a');

        $this->putRows($handle, [], $this->headers ?: $existingHeaders);

        fclose($handle);

        return $this;
    }

    /**
     * @param  resource  $handle
     * @param  array  $headerRow  Written as the first row. Empty writes no header.
     * @param  array  $columnOrder  Column order for associative rows.
     */
    protected function putRows($handle, array $headerRow, array $columnOrder): void
    {
        if ($headerRow !== []) {
            fputcsv($handle, $headerRow, $this->delimiter, escape: '\\');
        }

        foreach ($this->data as $row) {
            fputcsv($handle, $this->alignRow((array) $row, $columnOrder), $this->delimiter, escape: '\\');
        }
    }

    /**
     * Put an associative row into column order. A column the row does not carry
     * is written empty — never as a placeholder that reads like real data.
     */
    protected function alignRow(array $row, array $columnOrder): array
    {
        if ($columnOrder === [] || array_is_list($row)) {
            return $row;
        }

        return array_map(fn ($column) => $row[$column] ?? '', $columnOrder);
    }

    /**
     * The header already present in the target file, or null when there is
     * nothing to append to.
     */
    protected function headerRowInFile(): ?array
    {
        $this->assertTargetIsSet();

        if (! is_file($this->filePath) || filesize($this->filePath) === 0) {
            return null;
        }

        $handle = fopen($this->filePath, 'r');
        $header = fgetcsv($handle, null, $this->delimiter, escape: '\\');
        fclose($handle);

        return $header === false ? null : $header;
    }

    /**
     * @return resource
     */
    protected function openTarget(string $mode)
    {
        $this->assertTargetIsSet();

        $directory = dirname($this->filePath);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new \RuntimeException("Cannot write to directory: {$directory}");
        }

        if (is_file($this->filePath) && ! is_writable($this->filePath)) {
            throw new \RuntimeException("Cannot write to file: {$this->filePath}");
        }

        return fopen($this->filePath, $mode);
    }

    protected function assertTargetIsSet(): void
    {
        if ($this->filePath === null) {
            throw new \RuntimeException('No target file set, call toFile() before writing.');
        }
    }
}
