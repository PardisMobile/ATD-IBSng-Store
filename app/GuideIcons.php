<?php
declare(strict_types=1);
function guide_icon_svg(string $name, string $class = 'guide-icon'): string {
    $icons = [
        'openvpn' => '<path d="M12 3 19 6v5c0 4.7-2.9 8-7 10-4.1-2-7-5.3-7-10V6l7-3Z"/><path d="m8.7 12 2.1 2.1 4.5-4.5"/>',
        'v2ray' => '<circle cx="12" cy="5" r="2.2"/><circle cx="5" cy="18" r="2.2"/><circle cx="19" cy="18" r="2.2"/><path d="m10.8 7-4.6 8.8M13.2 7l4.6 8.8M7.2 18h9.6"/>',
        'wireguard' => '<path d="M12 3 19 6v5c0 4.7-2.9 8-7 10-4.1-2-7-5.3-7-10V6l7-3Z"/><path d="M8 12h3l2-3 3 6h-3"/>',
        'generic' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.8 12h16.4M12 3.5c2.2 2.3 3.2 5.1 3.2 8.5s-1 6.2-3.2 8.5c-2.2-2.3-3.2-5.1-3.2-8.5s1-6.2 3.2-8.5Z"/>',
        'android' => '<path d="M7 9h10a2 2 0 0 1 2 2v6H5v-6a2 2 0 0 1 2-2Z"/><path d="m8 6-1.5-2M16 6l1.5-2M8 13h.01M16 13h.01M8 17v3M16 17v3M3 11v5M21 11v5"/>',
        'ios' => '<path d="M16.7 12.8c0-2.1 1.7-3.1 1.8-3.2a4 4 0 0 0-3.1-1.7c-1.3-.1-2.5.8-3.2.8-.7 0-1.7-.8-2.8-.8a4.2 4.2 0 0 0-3.5 2.1c-1.5 2.6-.4 6.5 1.1 8.6.7 1 1.5 2.1 2.6 2.1 1 0 1.4-.7 2.7-.7s1.7.7 2.8.7 1.8-1 2.5-2.1a9.4 9.4 0 0 0 1.2-2.4 3.7 3.7 0 0 1-2.1-3.4Z"/><path d="M14.8 5.8a3.8 3.8 0 0 0 .9-2.8 4 4 0 0 0-2.6 1.3 3.6 3.6 0 0 0-.9 2.7 3.5 3.5 0 0 0 2.6-1.2Z"/>',
        'windows' => '<path d="m3 5.5 8-1.1v7.1H3V5.5ZM13 4.2l8-1.2v8.5h-8V4.2ZM3 13h8v7.1l-8-1.1V13ZM13 13h8v8.5l-8-1.2V13Z"/>',
        'macos' => '<rect x="3.5" y="4" width="17" height="16" rx="3"/><path d="M12 4v16M12 12h8.5M7.5 9h.01M7.5 15h.01"/>',
        'linux' => '<path d="M12 3c-2.6 0-4 2.2-4 5v3c-1.7 1.3-3 3.4-3 5.5 0 2.5 1.6 4.5 3.5 4.5 1.3 0 2-.8 3.5-.8s2.2.8 3.5.8c1.9 0 3.5-2 3.5-4.5 0-2.1-1.3-4.2-3-5.5V8c0-2.8-1.4-5-4-5Z"/><path d="M9.5 11.5h.01M14.5 11.5h.01M10 15c1.3.8 2.7.8 4 0"/>'
    ];
    $path = $icons[$name] ?? $icons['generic'];
    $safeClass = preg_replace('/[^a-zA-Z0-9_-]/', '', $class) ?: 'guide-icon';
    return '<svg class="' . $safeClass . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}
function guide_method_icon_key(array $method): string {
    $allowed = ['openvpn','v2ray','wireguard','generic'];
    $icon = strtolower(trim((string)($method['icon'] ?? '')));
    if (in_array($icon, $allowed, true)) return $icon;
    $name = strtolower(trim((string)($method['name'] ?? '')));
    foreach (['openvpn','v2ray','wireguard'] as $known) if (str_contains($name, $known)) return $known;
    return 'generic';
}
