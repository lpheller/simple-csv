<?php

namespace Heller\SimpleCsv\Support;

class FileHandler
{
    protected $cachedContent;

    protected ?string $encoding = null;

    public function __construct(protected string $filePath) {}

    /**
     * Convert the file from this encoding to UTF-8 while reading, for example
     * 'Windows-1252' for a German Excel export.
     */
    public function encoding(?string $encoding): static
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * Open the file and return a stream resource
     *
     * @return resource $handle
     */
    public function openFile()
    {
        $handle = str_starts_with($this->filePath, 'http') ? $this->handleFromUrl() : $this->handleFromPath();

        // BOM first, on the raw bytes, so the filter never sees a rewind
        $handle = $this->skipBom($handle);

        if ($this->encoding !== null) {
            stream_filter_append($handle, "convert.iconv.{$this->encoding}/UTF-8");
        }

        return $handle;
    }

    /**
     * @return resource
     */
    protected function handleFromPath()
    {
        if (! is_file($this->filePath) || ! is_readable($this->filePath)) {
            throw new \RuntimeException("Failed to open CSV file: {$this->filePath}");
        }

        return fopen($this->filePath, 'r');
    }

    /**
     * Leave the stream positioned after a UTF-8 BOM, so it never ends up in
     * the first header name. Excel and Google Sheets both write one.
     *
     * @param  resource  $handle
     * @return resource
     */
    protected function skipBom($handle)
    {
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        return $handle;
    }

    protected function handleFromUrl()
    {
        if ($this->cachedContent === null) {
            $this->cachedContent = $this->fetchFromUrl();
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, (string) $this->cachedContent);
        rewind($handle);

        return $handle;
    }

    protected function fetchFromUrl()
    {
        $this->checkForGoogleDriveUrl();

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to fetch CSV from URL: {$this->filePath}");
        }

        return $content;
    }

    protected function checkForGoogleDriveUrl()
    {
        if (str_contains($this->filePath, 'docs.google.com/spreadsheets')) {
            $this->filePath = $this->ensureGoogleCsvUrl($this->filePath);
        }
    }

    protected function ensureGoogleCsvUrl(string $url)
    {
        $query = [
            'output' => 'csv',
            'gid' => 0,
        ];

        if ($existingQuery = parse_url($url, PHP_URL_QUERY)) {
            parse_str($existingQuery, $output);
            $query = array_merge($query, $output);
        }

        $urlParts = parse_url($url);

        $urlParts['path'] = str_replace('/edit', '', $urlParts['path']);

        return "{$urlParts['scheme']}://{$urlParts['host']}{$urlParts['path']}/pub?".http_build_query($query);

    }
}
