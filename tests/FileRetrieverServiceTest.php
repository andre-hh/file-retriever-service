<?php

declare(strict_types=1);

namespace Tests;

use ColinODell\PsrTestLogger\TestLogger;
use FileRetrieverService\Exceptions\FileRetrievalFailedException;
use FileRetrieverService\Services\FileRetrieverService;
use phpseclib3\Exception\UnableToConnectException;
use PHPUnit\Framework\TestCase;

final class FileRetrieverServiceTest extends TestCase
{
    private FileRetrieverService $fileRetrieverService;

    public function setUp(): void
    {
        $this->fileRetrieverService = new FileRetrieverService(new TestLogger());

        parent::setUp();
    }

    /**
     * @throws \Exception
     */
    public function testUnzipFileContentsIfNecessary(): void
    {
        // A file that is pure text and not ending in .zip should not be unzipped
        self::assertEquals(
            'some content',
            $this->fileRetrieverService->unzipFileContentsIfNecessary(
                'http://www.example.com/sample.tsv',
                'some content',
                'tmp_'.microtime(true)
            )
        );

        // A file that is pure text but ending in .zip should not be unzipped
        self::assertEquals(
            'some content',
            $this->fileRetrieverService->unzipFileContentsIfNecessary(
                'http://www.example.com/sample.tsv.zip',
                'some content',
                'tmp_'.microtime(true)
            )
        );

        // A file that is zipped and ending in .zip should be unzipped
        self::assertEquals(
            'some zipped content',
            $this->fileRetrieverService->unzipFileContentsIfNecessary(
                'http://www.example.com/file.tsv.zip',
                file_get_contents('tests/file.tsv.zip'),
                'tmp_'.microtime(true)
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

    public function testRetrieveFileWrapsForeignExceptionsIntoFileRetrievalFailedException(): void
    {
        try {
            // Port 1 on localhost is closed, so phpseclib throws UnableToConnectException immediately
            $this->fileRetrieverService->retrieveFile(
                'sftp://user:pass@127.0.0.1:1/some-file.tsv',
                'tmp_'.microtime(true),
                'UTF-8',
                1,
                0,
            );

            self::fail('Expected FileRetrievalFailedException to be thrown.');
        } catch (FileRetrievalFailedException $e) {
            self::assertSame('sftp://user:pass@127.0.0.1:1/some-file.tsv', $e->getFileUrl());
            self::assertSame(UnableToConnectException::class, $e->getAdditionalData()['originalException']);
            self::assertInstanceOf(UnableToConnectException::class, $e->getPrevious());
        }
    }
}
