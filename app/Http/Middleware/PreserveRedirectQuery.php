<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PreserveRedirectQuery
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Hanya proses jika response berupa redirect HTTP standar (301, 302, dsb.)
        if ($response instanceof RedirectResponse) {
            $targetUrl = $response->getTargetUrl();

            // Cek apakah ada query string yang dikirim via input khusus '_return_query'
            $returnQuery = $request->input('_return_query');

            // Jika tidak ada di input, cek apakah referer memiliki query string
            if (!$returnQuery && $request->headers->has('referer')) {
                $referer = $request->headers->get('referer');
                $refererParts = parse_url($referer);
                if (!empty($refererParts['query'])) {
                    $targetPath = parse_url($targetUrl, PHP_URL_PATH);
                    $refererPath = $refererParts['path'] ?? '';
                    
                    // Jika path tujuan sama dengan referer (misal redirect back / index)
                    if ($targetPath === $refererPath || str_starts_with($refererPath, $targetPath)) {
                        $returnQuery = $refererParts['query'];
                    }
                }
            }

            if ($returnQuery) {
                // Parse return query menjadi array
                parse_str(ltrim($returnQuery, '?'), $newParams);

                // Parse target URL saat ini
                $targetParsed = parse_url($targetUrl);
                $existingQuery = [];
                if (!empty($targetParsed['query'])) {
                    parse_str($targetParsed['query'], $existingQuery);
                }

                // Gabungkan parameter: parameter eksisting di redirect diprioritaskan,
                // sisanya dilengkapi oleh parameter filter/paginasi dari returnQuery
                $mergedQuery = array_merge($newParams, $existingQuery);

                if (!empty($mergedQuery)) {
                    $queryString = http_build_query($mergedQuery);
                    
                    // Susun ulang target URL dengan query parameter lengkap
                    $scheme   = isset($targetParsed['scheme']) ? $targetParsed['scheme'] . '://' : '';
                    $host     = $targetParsed['host'] ?? '';
                    $port     = isset($targetParsed['port']) ? ':' . $targetParsed['port'] : '';
                    $user     = $targetParsed['user'] ?? '';
                    $pass     = isset($targetParsed['pass']) ? ':' . $targetParsed['pass'] : '';
                    $pass     = ($user || $pass) ? "$pass@" : '';
                    $path     = $targetParsed['path'] ?? '';
                    $fragment = isset($targetParsed['fragment']) ? '#' . $targetParsed['fragment'] : '';

                    $reconstructedUrl = "$scheme$user$pass$host$port$path?$queryString$fragment";
                    $response->setTargetUrl($reconstructedUrl);
                }
            }
        }

        return $response;
    }
}
