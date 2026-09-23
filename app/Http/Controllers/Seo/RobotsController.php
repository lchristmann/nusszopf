<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * `public/robots.txt`, with the sitemap on this instance's own host
 * (BUG-035) — hence a route rather than a static file.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        return response("User-agent: *\nSitemap: {$sitemap}\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
