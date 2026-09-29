<?php

namespace App\View\Components;

use App\Models\IdCardPrint;
use Illuminate\View\Component;

/**
 * The "how long ago was this ID card printed" pill on the bulk print page.
 * Distinct from expiry: this is the age of the latest (non-deleted) print
 * record, i.e. when someone last clicked "Mark as Printed".
 *
 * Takes the already-loaded latest IdCardPrint (not the user) so callers can
 * pass it from an eager-loaded collection instead of re-querying per card.
 *
 * Records imported by MigrateIdCardPrints get a muted asterisk: their date
 * comes from the old system, not from a confirmed print in this one.
 */
class PrintAgeIndicator extends Component
{
    /** Notes value written by MigrateIdCardPrints on every imported record. */
    public const LEGACY_NOTES = 'Record migrated from old database user data.';

    /** 'never_printed' | 'future_date' | 'fresh' | 'aging' | 'stale' */
    public string $state;

    public string $icon;

    public string $colorClass;

    public string $text;

    /** $text split into [headline, detail] for the stacked compact layout. */
    public array $lines;

    public bool $isLegacy;

    public bool $compact;

    public function __construct(?IdCardPrint $print = null, bool $compact = false)
    {
        $this->compact = $compact;
        $printedAt = $print?->printed_at;

        if (! $printedAt) {
            $this->state = 'never_printed';
            $this->icon = 'fa-circle-minus';
            $this->colorClass = 'bg-gray-100 text-gray-500';
            $this->text = 'Not printed';
            $this->lines = ['Not', 'printed'];
            $this->isLegacy = false;

            return;
        }

        // The migration writes both markers; either one is enough ('printed'
        // from "Mark as Printed" is lowercase).
        $this->isLegacy = $print->status === 'Printed' || $print->notes === self::LEGACY_NOTES;

        // A few migrated records carry future dates (bad old-system data);
        // show them as such rather than as a misleading "N mo ago".
        if ($printedAt->isFuture() && ! $printedAt->isCurrentMonth()) {
            $this->state = 'future_date';
            $this->icon = 'fa-circle-question';
            $this->colorClass = 'bg-gray-100 text-gray-500';
            $this->text = 'Future print date';
            $this->lines = ['Future', 'print date'];

            return;
        }

        // Same month math as $lastIdCardPaymentDisplay on the bulk print page;
        // floored at 1 so a print late last month never reads "0 mo ago".
        $months = $printedAt->isCurrentMonth()
            ? 0
            : (int) max(1, round($printedAt->diffInMonths(now(), true)));

        $this->state = $months < 12 ? 'fresh' : ($months <= 36 ? 'aging' : 'stale');

        [$this->icon, $this->colorClass] = match ($this->state) {
            'fresh' => ['fa-circle-check', 'bg-green-50 text-green-600'],
            'aging' => ['fa-circle-exclamation', 'bg-yellow-50 text-yellow-600'],
            'stale' => ['fa-triangle-exclamation', 'bg-red-50 text-red-600'],
        };

        $this->lines = ['Printed', $months === 0 ? 'this month' : "{$months} mo ago"];
        $this->text = implode(' ', $this->lines);
    }

    public function render()
    {
        return view('components.print-age-indicator');
    }
}
