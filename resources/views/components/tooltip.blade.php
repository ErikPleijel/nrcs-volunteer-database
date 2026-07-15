@props([
    'text',              // tooltip content
    'position' => 'top', // top | right | bottom | left | auto-x
])
@php
    // Base vertical / horizontal positioning (without left/right for auto-x)
    $positionClasses = match($position) {
        'right'  => 'left-full ml-2 top-1/2 -translate-y-1/2',
        'bottom' => 'top-full mt-2 left-1/2 -translate-x-1/2',
        'left'   => 'right-full mr-2 top-1/2 -translate-y-1/2',
        'auto-x' => 'top-1/2 -translate-y-1/2', // horizontal positioning will be added via JS
        default  => 'bottom-full mb-2 left-1/2 -translate-x-1/2', // top
    };

    // Generate unique ID for this tooltip
    $tooltipId = 'tooltip-' . uniqid();
@endphp

<div class="relative inline-flex items-center group tooltip-container"
     @if($position === 'auto-x') data-tooltip-id="{{ $tooltipId }}" data-position="{{ $position }}" @endif>
    {{-- Trigger (icon or any content you pass in) --}}
    <span class="inline-flex items-center">
        {{ $slot }}
    </span>

    {{-- Tooltip bubble --}}
    <div
        id="{{ $tooltipId }}"
        class="pointer-events-none absolute {{ $positionClasses }}
               w-64 max-w-[calc(100vw-2rem)] md:max-w-xs text-[11px]
               bg-gray-900 text-white rounded-md px-3 py-2 shadow-lg
               opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100
               transition-opacity z-20 tooltip-bubble"
    >
        {!! $text !!}
    </div>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Find all tooltip containers with auto-x positioning
            const autoXContainers = document.querySelectorAll('.tooltip-container[data-position="auto-x"]');

            // Function to position a tooltip
            function positionTooltip(container) {
                // Get the tooltip ID from the container
                const tooltipId = container.getAttribute('data-tooltip-id');
                if (!tooltipId) return;

                // Find the tooltip element
                const tooltip = document.getElementById(tooltipId);
                if (!tooltip) return;

                // Get position info
                const containerRect = container.getBoundingClientRect();
                const viewportWidth = window.innerWidth;

                // Remove any existing positioning classes
                tooltip.classList.remove('left-full', 'ml-2', 'right-full', 'mr-2');

                // Determine available space
                // We use 256px as an estimate for the tooltip width (w-64 = 16rem = 256px)
                const spaceOnRight = viewportWidth - containerRect.right;
                const tooltipWidth = 256;

                // Apply appropriate positioning
                if (spaceOnRight < tooltipWidth + 20) {
                    tooltip.classList.add('right-full', 'mr-2');
                } else {
                    tooltip.classList.add('left-full', 'ml-2');
                }
            }

            // Position all tooltips on page load
            autoXContainers.forEach(container => {
                // Position on load
                positionTooltip(container);

                // Re-position on hover and focus
                container.addEventListener('mouseenter', () => positionTooltip(container));
                container.addEventListener('focusin', () => positionTooltip(container));
            });

            // Update positions on window resize
            window.addEventListener('resize', () => {
                autoXContainers.forEach(container => positionTooltip(container));
            });

            const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

            document.querySelectorAll('.tooltip-container').forEach(container => {
                container.addEventListener('click', function(event) {
                    if (isMobile()) {
                        // Don't let the click bubble up to the document handler
                        event.stopPropagation();

                        const tooltipBubble = this.querySelector('.tooltip-bubble');
                        if (!tooltipBubble) return;

                        const isVisible = tooltipBubble.classList.contains('opacity-100');

                        // Hide all other tooltips
                        document.querySelectorAll('.tooltip-bubble').forEach(tb => {
                            if (tb !== tooltipBubble) {
                                tb.classList.remove('opacity-100');
                            }
                        });

                        // Toggle the clicked tooltip
                        tooltipBubble.classList.toggle('opacity-100');
                    }
                });
            });

            // Global click handler to close tooltips on mobile
            document.addEventListener('click', function() {
                if (isMobile()) {
                    document.querySelectorAll('.tooltip-bubble').forEach(tooltip => {
                        tooltip.classList.remove('opacity-100');
                    });
                }
            });
        });
    </script>
@endonce
