<?php

namespace App\Http\Controllers\Web\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MarketingPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = config("pages.pages.{$slug}");

        abort_unless($page && view()->exists("marketing.pages.{$slug}"), 404);

        return view("marketing.pages.{$slug}", compact('page'));
    }

    public function sitemap(): Response
    {
        $siteUrl = rtrim(config('pages.site_url'), '/');
        $siteUrl = preg_replace('#^(https?://)(?!www\.)#', '$1www.', $siteUrl);
        $pages = collect(config('pages.pages'))
            ->filter(fn (array $page) => ! empty($page['sitemap']))
            ->sortByDesc('priority');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $page) {
            $loc = $page['path'] === '/' ? $siteUrl : $siteUrl . $page['path'];
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . e($loc) . "</loc>\n";
            $xml .= '    <changefreq>' . e($page['changeFrequency'] ?? 'monthly') . "</changefreq>\n";
            $xml .= '    <priority>' . e((string) ($page['priority'] ?? 0.5)) . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
