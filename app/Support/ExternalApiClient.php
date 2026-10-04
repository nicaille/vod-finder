<?php

namespace App\Support;

use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class ExternalApiClient
{
    public static function make(): PendingRequest
    {
        // Respect cURL's explicit trust configuration; otherwise resolve the
        // system certificates, with Composer's maintained bundle as a fallback.
        $caBundle = ini_get('curl.cainfo') ?: CaBundle::getSystemCaRootBundlePath();

        return Http::timeout(10)->withOptions(['verify' => $caBundle]);
    }

    public static function failureContext(Throwable $exception): array
    {
        // HTTP exception messages and traces can contain API keys in request URLs.
        $context = ['exception_class' => get_class($exception), 'code' => $exception->getCode()];

        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof \GuzzleHttp\Exception\RequestException
                || $current instanceof \GuzzleHttp\Exception\ConnectException) {
                $handlerContext = $current->getHandlerContext();
                if (isset($handlerContext['errno'])) {
                    $context['curl_errno'] = (int) $handlerContext['errno'];
                }
                if ($current instanceof \GuzzleHttp\Exception\RequestException && $current->hasResponse()) {
                    $context['http_status'] = $current->getResponse()->getStatusCode();
                }
            }
        }

        return $context;
    }
}
