<?php

namespace Heller\SimpleCsv;

use Heller\SimpleCsv\Support\FileHandler;

class CsvWriter
{
    protected $data = [];

    protected $headers = [];

    protected $filePath;

    protected $fileHandler;

    public function __construct(array $data, $options = [])
    {
        $this->data = $data;
    }

    public function write($append = false)
    {
        $fileMode = $append ? 'a+' : 'w';
        $handle = fopen($this->filePath, $fileMode);

        if ($handle === false) {
            throw new \Exception("Could not open file: $this->filePath");
        }

        if ($append === true) {
            $headers = fgetcsv($handle);
            $this->headers = $headers;
        }

        if ($this->headers !== [] && $append === false) {
            fputcsv($handle, $this->headers);
        }

        foreach ($this->data as $row) {
            if ($this->headers !== []) {
                $row = $this->normalizeRowWithHeaders($row);
            }

            fputcsv($handle, $row);

        }

        fclose($handle);
    }

    public function toFile(string $filePath)
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function append()
    {
        return $this->write(true);
    }

    public function withHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    protected function normalizeRowWithHeaders($row)
    {
        // if the keys of the row are strings, then we need to merge the headers with the row
        // so that the keys are in the same order as the headers
        if (array_keys($row) !== range(0, count($row) - 1)) {
            // dd($this->headers);

            return array_merge(array_flip($this->headers), $row);
        }

        return $row;
    }

    protected function getFileHandler()
    {
        if ($this->fileHandler === null) {
            $this->fileHandler = new FileHandler($this->filePath);
        }

        return $this->fileHandler;
    }

    public function setFileHandler(FileHandler $fileHandler)
    {
        $this->fileHandler = $fileHandler;

        return $this;
    }

    public function insertRow(int $position, array $rowData)
    {
        $handle = $this->getFileHandler()->openFile();

        if ($handle === false) {
            return;
        }

        $tempFile = tmpfile();

        $this->copyDataUpToPosition($handle, $tempFile, $position);
        $this->writeNewRow($tempFile, $rowData);
        $this->copyRemainingData($handle, $tempFile);

        $this->rewindAndCopyBack($handle, $tempFile);

        fclose($handle);
        fclose($tempFile);
    }

    private function copyDataUpToPosition($handle, $tempFile, $position)
    {
        for ($i = 1; $i < $position; $i++) {
            $line = fgets($handle);
            fwrite($tempFile, $line);
        }
    }

    private function writeNewRow($tempFile, $rowData)
    {
        fputcsv($tempFile, $rowData);
    }

    private function copyRemainingData($handle, $tempFile)
    {
        while (($line = fgets($handle)) !== false) {
            fwrite($tempFile, $line);
        }
    }

    private function rewindAndCopyBack($handle, $tempFile)
    {
        rewind($handle);
        rewind($tempFile);

        while (($line = fgets($tempFile)) !== false) {
            fwrite($handle, $line);
        }
    }
}
