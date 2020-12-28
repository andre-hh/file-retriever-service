<?php
declare(strict_types=1);

namespace FileRetrieverService\Services;

use DateTime;
use Exception;
use FileRetrieverService\Exceptions\FileRetrievalFailedException;
use FileRetrieverService\FileEncoding;
use FileRetrieverService\Models\RetrievedFile;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class FileRetrieverService
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    /**
     * Copies the file from the specified URI to the specified local path.
     * Converts the file's contents to UTF-8.
     *
     * @throws FileRetrievalFailedException
     * @throws Exception
     */
    public function retrieveFile(
        string $url,
        ?string $localPath = null,
        string $inputFileEncoding = FileEncoding::ENCODING_UTF_8,
        int $attempts = 3,
        int $waitSecondsMultipliedWithAttemptAfterFailure = 5,
        int $curlRequestTimeoutSeconds = 300
    ): RetrievedFile
    {
        $this->logger->debug('Will copy file from ' . $url . ' to local disk now.');

        if (!$localPath) {
            $localPath = (string) microtime(true);
        }

        [$contents, $lastModifiedAt] = $this->getRawFileContents(
            $url,
            $attempts,
            $waitSecondsMultipliedWithAttemptAfterFailure,
            $curlRequestTimeoutSeconds
        );

        $contents = $this->gzdecodeFileContentsIfNecessary($url, $contents);
        $contents = $this->unzipFileContentsIfNecessary($url, $contents, $localPath);

        if ($inputFileEncoding !== FileEncoding::ENCODING_UTF_8) {
            $contents = mb_convert_encoding($contents, FileEncoding::ENCODING_UTF_8, $inputFileEncoding);
        }

        file_put_contents($localPath, $contents);

        $this->logger->debug(
            'Retrieved and prepared file from ' . $url . ' at ' . $localPath . ' for further processing.'
        );

        return new RetrievedFile($url, $localPath, $lastModifiedAt, strlen($contents));
    }

    /**
     * Attempts to retrieve the contents from the given URL.
     *
     * @throws FileRetrievalFailedException
     * @throws Exception
     */
    protected function getRawFileContents(
        string $fileUrl,
        int $maxAttempts = 3,
        int $waitSecondsMultipliedWithAttemptAfterFailure = 5,
        int $curlRequestTimeoutSeconds = 300
    ): array
    {
        $attempt = 0;

        while (true) {

            $attempt++;

            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fileUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, $curlRequestTimeoutSeconds); // Big files might take some time!
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_ENCODING, 'gzip');

                // If this is not reliable, we might try get_headers() as described here:
                // http://stackoverflow.com/questions/845220/get-the-last-modified-date-of-a-remote-file
                curl_setopt($ch, CURLOPT_FILETIME, true);

                // This should avoid errors like "error #18: transfer closed with ... bytes remaining to read".
                // @see https://stackoverflow.com/questions/1759956/curl-error-18-transfer-closed-with-outstanding-read-data-remaining
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);

                $contents = curl_exec($ch);

                $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                if (in_array($responseCode, [403, 404,], false)) {
                    throw new FileRetrievalFailedException(
                        $fileUrl,
                        'Got ' . $responseCode . ' when retrieving file contents.'
                    );
                }

                $error = curl_errno($ch);
                if ($error > 0) {
                    throw new FileRetrievalFailedException(
                        $fileUrl,
                        'Got CURL error when retrieving file contents.',
                        ['curlErrorCode' => $error, 'curlErrorMessage' => curl_error($ch),]
                    );
                }

                if (!is_string($contents) || $contents === '') {
                    throw new FileRetrievalFailedException(
                        $fileUrl,
                        'Got empty file when retrieving file contents.'
                    );
                }

                // See comment above...
                $timestamp = curl_getinfo($ch, CURLINFO_FILETIME);  // Timestamps are always UTC
                $lastModifiedAt = ($timestamp !== -1) ? new DateTime('@' . $timestamp) : null;

                curl_close($ch);

                return [$contents, $lastModifiedAt,];

            } catch (FileRetrievalFailedException $e) {

                $context = [
                    'attempt' => $attempt,
                    'fileUrl' => $e->getFileUrl(),
                    'additionalData' => $e->getAdditionalData(),
                ];

                // We use different log levels to avoid too much noise
                if ($attempt === 1) {
                    $this->logger->info($e->getMessage(), $context);
                } else {
                    $this->logger->warning($e->getMessage(), $context);
                }

                if ($attempt === $maxAttempts) {
                    throw $e;
                } else {
                    sleep($attempt * $waitSecondsMultipliedWithAttemptAfterFailure);
                }
            }
        }
    }

    /**
     * Unzips a .zip file and returns the contents of the first file found in the zip archive.
     *
     * TODO: Add error handling
     * TODO: Add tests
     *
     * @throws Exception
     */
    public function unzipFileContentsIfNecessary(string $url, string $contents, string $localPath): string
    {
        if (substr_compare($url, '.zip', strlen($url) - strlen('.zip'), strlen('.zip')) === 0) {

            $this->logger->debug('Got a file ending in .zip. Trying to unzip.');

            file_put_contents($localPath . '-zipped', $contents);

            $zipArchive = new ZipArchive();
            $resource = $zipArchive->open($localPath . '-zipped');

            if ($resource !== true) {
                $this->logger->info(
                    'Skipped unzipping as the file is not a real zip file although ending in .zip.',
                    ['errorCode' => $resource,]
                );
            } else {

                // Unzip .zip file into directory
                $zipArchive->extractTo($localPath . '-unzipped');
                $zipArchive->close();

                $filesInZipArchive = array_values(
                    array_filter(scandir($localPath . '-unzipped'), function($item) use ($localPath) {
                        return !is_dir($localPath . '-unzipped/' . $item);
                    })
                );

                // TODO: Improve exception and log message
                if (count($filesInZipArchive) === 0) {
                    throw new RuntimeException('Zip archive is empty.');
                } elseif (count($filesInZipArchive) > 1) {
                    $this->logger->warning('More than one file in zip archive.');
                }

                // Get the contents of the first file
                // @see https://stackoverflow.com/questions/33446651/get-first-file-in-directory-php
                $contents = file_get_contents($localPath . '-unzipped/' . $filesInZipArchive[0]);

                $this->deleteDirectoryTree($localPath . '-unzipped');
            }

            unlink($localPath . '-zipped');
        }

        return $contents;
    }

    public function gzdecodeFileContentsIfNecessary(string $url, $contents): string
    {
        // gzdecode if file ends with .gz
        if (substr_compare($url, '.gz', strlen($url) - strlen('.gz'), strlen('.gz')) === 0) {

            $this->logger->debug('Got a file ending in .gz. Trying to gzdecode.');

            $isGzip = (0 === mb_strpos($contents, "\x1f" . "\x8b" . "\x08", 0, 'US-ASCII'));

            if ($isGzip) {
                $contents = gzdecode($contents);
            } else {
                $this->logger->info(
                    'Skipped gzdecoding as the file is not a real gzipped file although ending in .gz.'
                );
            }
        }

        return $contents;
    }

    /**
     * Recursively deletes a directory tree.
     *
     * @see https://gist.github.com/mindplay-dk/a4aad91f5a4f1283a5e2
     */
    private function deleteDirectoryTree(string $folder): bool
    {
        // Handle bad arguments.
        if (empty($folder) || !file_exists($folder)) {
            return true; // No such file/folder exists.
        } elseif (is_file($folder) || is_link($folder)) {
            return @unlink($folder);
        }

        // Delete all children.
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $action = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            if (!@$action($fileinfo->getRealPath())) {
                return false; // Abort due to the failure.
            }
        }

        return @rmdir($folder);
    }
}
