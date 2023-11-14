<?php

namespace Heller\SimpleCsv\Support;

class FileHandler
{
    protected $cachedContent;

    public function __construct(protected string $filePath)
    {
    }

    /**
     * Open the file and return a stream resource
     *
     * @return resource $handle
     */
    public function openFile()
    {
        return str_starts_with($this->filePath, 'http') ? $this->handleFromUrl() : fopen($this->filePath, 'r+');
    }

    protected function handleFromUrl()
    {
        if ($this->cachedContent === null) {
            $this->cachedContent = $this->fetchFromUrl();
        }

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, (string) $this->cachedContent);
        rewind($handle);

        return $handle;
    }

    protected function fetchFromUrl()
    {
        $this->checkForGoogleDriveUrl();

        // ray('hit n times');
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
