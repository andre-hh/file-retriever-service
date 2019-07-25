# File Retriever Service

This is a PHP service to retrieve file contents.

Some features:

 - Extracts zipped and gzipped files (and returns the first file from the extraction result)
 - Converts contents into UTF-8
 - Retries file retrieval with incrementing waiting periods
 - Determines the last modification date from the URL's HTTP headers


## Development

Run tests with `php vendor/phpunit/phpunit/phpunit --bootstrap vendor/autoload.php tests`.


## TODO

 - Increase test coverage
 - Perhaps add `curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'logDownloadProgress']);`
