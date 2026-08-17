<?php

namespace Heller\SimpleCsv;

class CsvWriter
{
    protected array $headers = [];

    protected ?string $filePath = null;

    protected string $delimiter = ',';

    protected string $lineEnding = "\n";

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
     * End rows with CRLF instead of LF, which is what Excel on Windows expects.
     */
    public function crlf(): static
    {
        $this->lineEnding = "\r\n";

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
     * Insert the data before the record at $position, counting from 1. The
     * header occupies record 1, so the first data row is position 2. Records
     * after the insert are copied byte for byte and keep their formatting.
     *
     * A position past the end of the file appends.
     */
    public function insertAt(int $position): static
    {
        if ($position < 1) {
            throw new \RuntimeException('Position must be 1 or higher.');
        }

        $existingHeaders = $this->headerRowInFile();

        if ($existingHeaders === null) {
            return $this->write();
        }

        $this->assertTargetIsWritable();

        $source = fopen($this->filePath, 'r');
        $offset = $this->offsetOfRecord($source, $position);

        $temporaryPath = tempnam(dirname($this->filePath), 'simple-csv');
        $target = fopen($temporaryPath, 'w');

        rewind($source);
        stream_copy_to_stream($source, $target, $offset);
        $this->putRows($target, [], $this->headers ?: $existingHeaders);
        stream_copy_to_stream($source, $target);

        fclose($source);
        fclose($target);

        chmod($temporaryPath, fileperms($this->filePath) & 0777);
        rename($temporaryPath, $this->filePath);

        return $this;
    }

    /**
     * Byte offset at which the record at $position starts. Records are counted
     * with fgetcsv, so a newline inside a quoted field does not shift the count.
     *
     * @param  resource  $source
     */
    protected function offsetOfRecord($source, int $position): int
    {
        $offset = 0;

        for ($record = 1; $record < $position; $record++) {
            if (fgetcsv($source, null, $this->delimiter, escape: '\\') === false) {
                break;
            }

            $offset = ftell($source);
        }

        return $offset;
    }

    /**
     * @param  resource  $handle
     * @param  array  $headerRow  Written as the first row. Empty writes no header.
     * @param  array  $columnOrder  Column order for associative rows.
     */
    protected function putRows($handle, array $headerRow, array $columnOrder): void
    {
        if ($headerRow !== []) {
            fputcsv($handle, $headerRow, $this->delimiter, escape: '\\', eol: $this->lineEnding);
        }

        foreach ($this->data as $row) {
            fputcsv($handle, $this->alignRow((array) $row, $columnOrder), $this->delimiter, escape: '\\', eol: $this->lineEnding);
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
        $this->assertTargetIsWritable();

        return fopen($this->filePath, $mode);
    }

    protected function assertTargetIsWritable(): void
    {
        $this->assertTargetIsSet();

        $directory = dirname($this->filePath);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new \RuntimeException("Cannot write to directory: {$directory}");
        }

        if (is_file($this->filePath) && ! is_writable($this->filePath)) {
            throw new \RuntimeException("Cannot write to file: {$this->filePath}");
        }
    }

    protected function assertTargetIsSet(): void
    {
        if ($this->filePath === null) {
            throw new \RuntimeException('No target file set, call toFile() before writing.');
        }
    }
}
