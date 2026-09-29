<?php

namespace App\Support;

/**
 * Default content of the footer's editable sections (settings
 * site.footer_contact_html / site.footer_quick_links_html): what the seeder
 * installs and what the footer shows if a row is ever missing. Plain
 * allowlisted HTML only (see SettingHtmlSanitizer) — the footer's scoped CSS
 * styles it.
 */
class FooterDefaults
{
    public const CONTACT_HTML = <<<'HTML'
<p><strong>Email:</strong> info@redcrossnigeria.org</p>
<p><strong>Phone Number (TOLL FREE)</strong><br>
(+234) 803 123 0430 (MTN)<br>
(+234) 809 993 7357 (9Mobile)</p>
<p><a href="https://www.redcrossnigeria.org/contact-us" target="_blank" rel="noopener">Contact form</a></p>
HTML;

    public const QUICK_LINKS_HTML = <<<'HTML'
<ul>
<li><a href="https://www.redcrossnigeria.org" target="_blank" rel="noopener">Nigerian Red Cross website</a></li>
<li><a href="https://www.redcrossnigeria.org/about-us" target="_blank" rel="noopener">About us</a></li>
</ul>
HTML;
}
