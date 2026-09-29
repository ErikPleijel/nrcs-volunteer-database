<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes admin-entered HTML settings (type 'html', e.g. the footer's
 * Contact Information and Quick Links), which are rendered unescaped on every
 * page. Applied when a setting is saved and again when it is rendered.
 *
 * Allowlist: strong, b, em, br, p, ul, li, and a with href / target / rel
 * only. No class, style or on* attributes on anything; links must be http,
 * https or mailto. A few harmless wrappers (UNWRAPPED) are removed but their
 * text kept; any other element is removed with its content.
 *
 * resources/views/settings/edit.blade.php mirrors this allowlist in its
 * client-side preview — keep the two in step.
 */
class SettingHtmlSanitizer
{
    /** Removed but their text kept, so wrapping text in them doesn't lose it. */
    public const UNWRAPPED = ['div', 'span', 'i', 'u', 'small'];

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('br')
            ->allowElement('p')
            ->allowElement('ul')
            ->allowElement('li')
            ->allowElement('a', ['href', 'target', 'rel'])
            ->allowLinkSchemes(['http', 'https', 'mailto']);

        foreach (self::UNWRAPPED as $element) {
            $config = $config->blockElement($element);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(?string $html): string
    {
        // Symfony also entity-encodes + @ =, which are harmless in HTML but
        // would show as &#43; etc. in the settings page's Code view.
        return str_replace(
            ['&#43;', '&#64;', '&#61;'],
            ['+', '@', '='],
            $this->sanitizer->sanitize((string) $html)
        );
    }
}
