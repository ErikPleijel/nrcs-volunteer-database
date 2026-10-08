<?php

namespace App\View\Components;

use App\Models\Setting;
use Illuminate\View\Component;

/**
 * The Data Protection Officer's contact box, read from the four dpo.*
 * settings (edited on the Settings page; not a role — Decisions.md
 * 2026-10-08).
 *
 * When no DPO name is set it shows a warning instead: staff who can change
 * settings get a link to fill it in, everyone else is asked to tell the
 * National DB Administrator. With :public="true" (the public privacy page)
 * it shows a neutral "will be published soon" note instead of the warning.
 */
class DpoContact extends Component
{
    public string $name;

    public string $address;

    public string $email;

    public string $phone;

    /** The phone number with only digits and a leading +, for the tel: link. */
    public string $phoneHref;

    public bool $public;

    public bool $canEditSettings;

    public function __construct(bool $public = false)
    {
        $this->public = $public;

        $this->name = trim((string) Setting::get('dpo.name', ''));
        $this->address = trim((string) Setting::get('dpo.address', ''));
        $this->email = trim((string) Setting::get('dpo.email', ''));
        $this->phone = trim((string) Setting::get('dpo.phone', ''));
        $this->phoneHref = preg_replace('/(?!^\+)[^\d]/', '', $this->phone);

        $this->canEditSettings = (bool) auth()->user()?->can('change_settings');
    }

    public function render()
    {
        return view('components.dpo-contact');
    }
}
