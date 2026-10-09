<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts()
    {
        $url = config('app.url');
        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);
        return $host ? ['^'.preg_quote($host, '/').'$'] : [];
    }
}
