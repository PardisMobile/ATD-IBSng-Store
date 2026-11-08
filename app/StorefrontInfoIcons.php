<?php
declare(strict_types=1);

/** Decorative SVGs for customer-facing information labels; separate from navigation and guide-method icons. */
function storefront_info_icon(string $name): string
{
    $paths = [
        'service' => '<path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/>',
        'price' => '<path d="M20 13 13 20 4 11V4h7z"/><circle cx="8" cy="8" r="1"/>',
        'duration' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'quota' => '<path d="M4 19V5M4 19h16"/><path d="m7 15 3-4 3 2 4-6"/>',
        'account' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'server' => '<rect x="3" y="3" width="18" height="8" rx="2"/><rect x="3" y="13" width="18" height="8" rx="2"/><path d="M7 7h.01M7 17h.01"/>',
        'plan' => '<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 5"/>',
        'status' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>',
        'date' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>',
        'remaining' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l-3 2"/>',
        'data' => '<path d="M4 6h16v12H4z"/><path d="M8 10h8M8 14h5"/>',
        'password' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
        'tracking' => '<path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h5M8 16h3"/>',
        'payment' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'guide' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 4 2c-1.2.8-1.5 1.2-1.5 2.5M12 17h.01"/>',
        'app' => '<rect x="6" y="3" width="12" height="18" rx="2"/><path d="M10 7h4M11 17h2"/>',
        'troubleshoot' => '<path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17h.01"/>',
    ];
    $path = $paths[$name] ?? $paths['service'];
    return '<svg class="store-info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}
