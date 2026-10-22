<?php

namespace App\View\Components;

use App\Services\ProfileResources;
use Illuminate\View\Component;

/**
 * The full-width "Useful links" and "Downloads" sections at the bottom of
 * My Profile, read from the two profile_resources settings (edited under
 * Settings → Useful Links & Downloads). A section with no items is hidden.
 */
class ProfileResourcesSection extends Component
{
    /** @var list<array{title: string, url: string, description: string, domain: string}> */
    public array $links;

    /** @var list<array{title: string, href: string, description: string, type: string, icon: string, note: string}> */
    public array $downloads;

    public function __construct()
    {
        $this->links = array_map(fn (array $link) => $link + [
            'domain' => ProfileResources::domain($link['url']),
        ], ProfileResources::links());

        $this->downloads = array_map(function (array $download) {
            [$type, $icon] = ProfileResources::fileType($download);

            return [
                'title' => $download['title'],
                'href' => $download['path'] !== null
                    ? route('downloads.show', $download['id'])
                    : $download['url'],
                'description' => $download['description'],
                'type' => $type,
                'icon' => $icon,
                'note' => $download['path'] !== null ? '' : ProfileResources::domain($download['url']),
            ];
        }, ProfileResources::downloads());
    }

    public function shouldRender(): bool
    {
        return $this->links !== [] || $this->downloads !== [];
    }

    public function render()
    {
        return view('components.profile-resources-section');
    }
}
