<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

class FakeHttpStream
{
    public static string $body = '';

    public static ?string $requestedUrl = null;

    public $context;

    private int $position = 0;

    public function stream_open(string $path): bool
    {
        self::$requestedUrl = $path;
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$body, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$body);
    }

    public function stream_stat(): array
    {
        return ['size' => strlen(self::$body)];
    }

    public function stream_close(): void {}
}

/**
 * Run $callback with http/https served from $body instead of the network.
 */
function fakeHttp(string $body, Closure $callback)
{
    FakeHttpStream::$body = $body;
    FakeHttpStream::$requestedUrl = null;

    foreach (['http', 'https'] as $scheme) {
        stream_wrapper_unregister($scheme);
        stream_wrapper_register($scheme, FakeHttpStream::class);
    }

    try {
        return $callback();
    } finally {
        foreach (['http', 'https'] as $scheme) {
            stream_wrapper_restore($scheme);
        }
    }
}
