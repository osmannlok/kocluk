<?php
// ============================================================
// GONCA LÖK — Satır içi SVG ikon seti (Phosphor tarzı, tek stroke)
// Kullanım: icon('calendar') cagrisiyla SVG ikon uretir
// ============================================================
declare(strict_types=1);

function icon(string $name, int $size = 20, string $class = ''): string
{
    static $paths = [
        'calendar' => '<rect x="3" y="4.5" width="18" height="17" rx="2.5"/><line x1="8" y1="2.5" x2="8" y2="7"/><line x1="16" y1="2.5" x2="16" y2="7"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'calendar-month' => '<rect x="3" y="4.5" width="18" height="17" rx="2.5"/><line x1="8" y1="2.5" x2="8" y2="7"/><line x1="16" y1="2.5" x2="16" y2="7"/><line x1="3" y1="10" x2="21" y2="10"/><circle cx="12" cy="15" r="1.4" fill="currentColor" stroke="none"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.3" fill="currentColor" stroke="none"/>',
        'motivation' => '<path d="M12 2.5l2.6 5.6 6.1.5-4.7 4 1.4 6-5.4-3.2-5.4 3.2 1.4-6-4.7-4 6.1-.5z"/>',
        'sparkles' => '<path d="M12 3l1.8 4.6L18.4 9l-4.6 1.4L12 15l-1.8-4.6L5.6 9l4.6-1.4z"/><path d="M19 13l.9 2.2L22 16l-2.1.9L19 19l-.9-2.1L16 16l2.1-.8z"/>',
        'handshake' => '<path d="M8 11l3-3c1.5-1.5 3.5-1.5 5 0l3 3"/><path d="M4 12l4-4"/><path d="M20 12l-4-4"/><path d="M6 13l6 6"/><path d="M18 13l-6 6"/><path d="M10 21l2-2 2 2"/>',
        'users' => '<circle cx="9" cy="8.5" r="3.5"/><path d="M3.5 20c.5-3.8 2.8-6 5.5-6s5 2.2 5.5 6"/><circle cx="17.5" cy="9.5" r="2.8"/><path d="M15.5 14.4c2.9.3 4.6 2.3 5 5.6"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13.5l5 2v4a2 2 0 0 1-2.2 2A17 17 0 0 1 3 6.2 2 2 0 0 1 5 4z"/>',
        'mail' => '<rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 6.5l9 6.5 9-6.5"/>',
        'message' => '<path d="M21 12a8 8 0 0 1-8 8H5l-2 2V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8z"/>',
        'check' => '<path d="M4 12.5l5.5 5.5L20 6.5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7"/><path d="M6.5 7l1 14a2 2 0 0 0 2 1.8h5a2 2 0 0 0 2-1.8l1-14"/>',
        'pencil' => '<path d="M4 20l1-4.5L15.5 5a2.1 2.1 0 0 1 3 3L8.5 18.5 4 20z"/>',
        'eye' => '<path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3.2"/>',
        'file-text' => '<path d="M13 2.5H6.5A1.5 1.5 0 0 0 5 4v16a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 20V7.5z"/><path d="M13 2.5v5H19"/><path d="M8.5 13h7M8.5 17h7M8.5 9h2.5"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'arrow-right' => '<path d="M4 12h16"/><path d="M13 5l7 7-7 7"/>',
        'arrow-left' => '<path d="M20 12H4"/><path d="M11 5l-7 7 7 7"/>',
        'phone-call' => '<path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13.5l5 2v4a2 2 0 0 1-2.2 2A17 17 0 0 1 3 6.2 2 2 0 0 1 5 4z"/><path d="M14 3.5a5.5 5.5 0 0 1 5.5 5.5"/>',
        'external' => '<path d="M14 4h6v6"/><path d="M20 4L11 13"/><path d="M9 6H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-4"/>',
        'camera' => '<path d="M4 8h3l2-2.5h6L17 8h3a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.6"/>',
        'alert' => '<path d="M12 3.5L2.5 20h19L12 3.5z"/><path d="M12 10v4.5"/><path d="M12 17.5h.01"/>',
        'lock' => '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M11 12l9.5-9.5"/><path d="M17 4l3 3M14 7l3 3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10"/>',
        'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 5.5v15"/>',
        'briefcase' => '<rect x="2.5" y="7" width="19" height="13" rx="2.5"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M2.5 13h19"/>',
        'graduation' => '<path d="M2.5 9.5L12 4.5l9.5 5-9.5 5z"/><path d="M6.5 12v5c0 1.5 2.5 3 5.5 3s5.5-1.5 5.5-3v-5"/><path d="M21.5 9.5V16"/>',
        'chart' => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-8"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'mail-open' => '<rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 7l9 7 9-7"/>',
        'bell' => '<path d="M18 9a6 6 0 1 0-12 0c0 6-2.5 7-2.5 7h17S18 15 18 9z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
        'star' => '<path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z"/>',
        'star-fill' => '<path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/>',
        'chevron-left' => '<path d="M15 18l-6-6 6-6"/>',
        'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
        'wrench' => '<path d="M15 4.5a4.5 4.5 0 0 0-4.5 4.5l-7.3 7.3a2.1 2.1 0 0 0 3 3l7.3-7.3A4.5 4.5 0 0 0 15 4.5z"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];
    $d = $paths[$name] ?? $paths['file-text'];
    $cls = 'icon' . ($class !== '' ? ' ' . $class : '');
    return '<svg class="' . $cls . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}
