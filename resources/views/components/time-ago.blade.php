@props(['date', 'today' => false])
@if(!$date)
    N/A
@else
    @php
        $carbon = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        $isFuture = $carbon->isFuture();

        $yr  = (int) abs($carbon->diffInYears(now()));
        $mo  = (int) abs($carbon->diffInMonths(now()));
        $d   = (int) abs($carbon->diffInDays(now()));
        $h   = (int) abs($carbon->diffInHours(now()));
        $min = (int) abs($carbon->diffInMinutes(now()));

        if ($today && $carbon->isToday()) {
            $label = 'today';
        } elseif ($isFuture) {
            if ($yr > 0)      $label = '+' . $yr . 'y';
            elseif ($mo > 0)  $label = '+' . $mo . 'mo';
            elseif ($d > 0)   $label = '+' . $d  . 'd';
            elseif ($h > 0)   $label = '+' . $h  . 'h';
            else              $label = '+' . $min . 'min';
        } elseif ($yr > 0)    $label = $yr  . 'yr';
        elseif ($mo > 0)      $label = $mo  . 'mo';
        elseif ($d > 0)       $label = $d   . 'd';
        elseif ($h > 0)       $label = $h   . 'h';
        elseif ($min > 0)     $label = $min . 'min';
        else                  $label = 'now';
    @endphp
    {{ $carbon->format('j M Y') }} ({{ $label }})
@endif
