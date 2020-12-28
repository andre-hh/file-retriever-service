<?php
declare(strict_types=1);

namespace Tests;

use Exception;
use FileRetrieverService\Services\FileRetrieverService;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

final class FileRetrieverServiceTest extends TestCase
{
    private FileRetrieverService $fileRetrieverService;

    public function setUp(): void
    {
        $this->fileRetrieverService = new FileRetrieverService(new TestLogger());

        parent::setUp();
    }

    /**
     * @throws Exception
     */
    public function testUnzipFileContentsIfNecessary(): void
    {
        // A file that is pure text and not ending in .zip should not be unzipped
        self::assertEquals(
            'some content',
            $this->fileRetrieverService->unzipFileContentsIfNecessary(
                'http://www.example.com/sample.tsv',
                'some content',
                'tmp_' . microtime(true)
            )
        );

        // A file that is pure text but ending in .zip should not be unzipped
        self::assertEquals(
            'some content',
            $this->fileRetrieverService->unzipFileContentsIfNecessary(
                'http://www.example.com/sample.tsv.zip',
                'some content',
                'tmp_' . microtime(true)
            )
        );

        // A file that is zipped and ending in .zip should be unzipped
        self::assertEquals(
            'some zipped content',
            $this->fileRetrieverService->unzipFileContentsIfNecessary(
                'http://www.example.com/file.tsv.zip',
                file_get_contents('tests/file.tsv.zip'),
                'tmp_' . microtime(true)
            )
        );

        // TODO: Test with a file that is zipped but not ending in .zip (we won't detect this yet)
        // TODO: Test with a file that is gzipped but ending in .zip (we won't detect this yet)
    }

    public function testGzdecodeFileContentsIfNecessary(): void
    {
        // A file that is pure text and not ending in .gz should not be gzdecoded
        self::assertEquals(
            'some content',
            $this->fileRetrieverService->gzdecodeFileContentsIfNecessary(
                'http://www.example.com/sample.tsv',
                'some content'
            )
        );

        // A file that is pure text but ending in .gz should not be gzdecoded
        self::assertEquals(
            'some content',
            $this->fileRetrieverService->gzdecodeFileContentsIfNecessary(
                'http://www.example.com/sample.tsv.gz',
                'some content'
            )
        );

        // A file that is gzdecoded and ending in .gz should be gzdecoded
        $this->assertEquals(
            'some gzipped content',
            $this->fileRetrieverService->gzdecodeFileContentsIfNecessary(
                'http://www.example.com/file.tsv.gz',
                file_get_contents('tests/file.tsv.gz')
            )
        );

        // TODO: Test with a file that is zipped but not ending in .zip (we won't detect this yet)
        // TODO: Test with a file that is gzipped but ending in .zip (we won't detect this yet)
    }
}
