<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
@switch($icon)
@case('alarm')<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M4 3L1 6M20 3l3 3M6 20l-1 2M18 20l1 2"/>@break
@case('heart')<path d="M12 21l-8-8a5 5 0 0 1 8-7 5 5 0 0 1 8 7z"/>@break
@case('list')<path d="M4 5h10M4 10h10M4 15h6M17 13v8M13 17h8"/>@break
@case('forward')<path d="M14 5l7 7-7 7M21 12H9a6 6 0 0 0-6 6"/>@break
@endswitch
</svg>
