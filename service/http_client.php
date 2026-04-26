<?php

declare(strict_types=1);

/**
 *
 * Spamtroll Anti-Spam extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Spamtroll
 * @license   GPL-2.0-only
 *
 */

namespace spamtroll\phpbb\service;

use Spamtroll\Sdk\Exception\ConnectionException;
use Spamtroll\Sdk\Exception\TimeoutException;
use Spamtroll\Sdk\Http\HttpClientInterface;
use Spamtroll\Sdk\Http\HttpResponse;

/**
 * cURL-backed HTTP adapter for the Spamtroll PHP SDK.
 *
 * phpBB ships its own \phpbb\file_downloader, but it doesn't fit cleanly:
 * it expects the body via GET parameters and doesn't expose the status
 * code in a usable way. A small dedicated cURL client is simpler and
 * keeps the dependency surface minimal.
 */
class http_client implements HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout
    ): HttpResponse {
        $ch = curl_init();
        if ($ch === false) {
            throw ConnectionException::fromMessage('Failed to initialise cURL handle');
        }

        $curl_headers = [];
        foreach ($headers as $name => $value) {
            $curl_headers[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($method === 'POST' && $body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            // Map cURL timeout codes to a TimeoutException so the SDK can
            // distinguish "the wire is slow" from "the server is broken".
            if ($errno === CURLE_OPERATION_TIMEOUTED) {
                throw TimeoutException::afterSeconds($timeout);
            }
            throw ConnectionException::fromMessage(
                $error !== '' ? $error : 'cURL transport error'
            );
        }

        return new HttpResponse($status, (string) $raw, []);
    }
}
