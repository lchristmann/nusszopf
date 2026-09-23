<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Response;

/**
 * `pages/api/sitemap.js` (served at `/sitemap.xml` by a `next.config.js`
 * rewrite): Home, Impressum and Datenschutz, then every public project with
 * its `updated_at`. The host is `APP_URL` (BUG-035), not nusszopf.org.
 * Always the guest's view, whoever asks.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $domain = rtrim((string) config('app.url'), '/');

        $urls = [
            ['loc' => $domain.'/'],
            ['loc' => $domain.'/legalNotice'],
            ['loc' => $domain.'/privacy'],
        ];

        foreach (Project::visible(null)->orderBy('created_at')->get(['id', 'updated_at']) as $project) {
            $urls[] = [
                'loc' => $domain.'/projects/'.$project->id,
                'lastmod' => $project->updated_at?->utc()->format('Y-m-d\TH:i:s.v\Z'),
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $xml .= '<url><loc>'.e($url['loc']).'</loc>'
                .(isset($url['lastmod']) ? '<lastmod>'.$url['lastmod'].'</lastmod>' : '')
                .'</url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'text/xml']);
    }
}
