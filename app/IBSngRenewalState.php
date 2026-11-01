<?php
declare(strict_types=1);

/**
 * Strict read-only normalization of IBSng A1.24 account expiration data.
 * DOM parsing is aligned with the selected RouteBox renewal reference.
 */
final class IBSngRenewalState
{
    public static function fromClient(IBSngClient $client, string $username): array
    {
        $user = $client->lookupUser($username);
        $html = (string)($user['html'] ?? '');
        if ($html === '') {
            throw new RuntimeException('IBSng user information markup was not available.');
        }
        $firstLogin = self::attributeValue($html, 'first_login');
        $relativeExpiration = self::attributeValue($html, 'rel_exp');
        $nearestExpiration = self::displayedLabelValue($html, 'Nearest Expiration Date');
        return self::normalize((string)$user['user_id'], $firstLogin, $relativeExpiration, $nearestExpiration);
    }

    public static function normalize(string $userId, ?string $firstLogin, ?string $relativeExpiration, ?string $nearestExpiration, ?int $now = null): array
    {
        if (trim($userId) === '') {
            throw new RuntimeException('IBSng user ID is missing.');
        }
        $empty = ['', '---------------', 'N/A', '-'];
        $first = trim((string)$firstLogin);
        $relative = trim((string)$relativeExpiration);
        $nearest = trim((string)$nearestExpiration);
        $firstValue = self::isEmptyValue($first, $empty) ? null : $first;
        $nearestValue = self::isEmptyValue($nearest, $empty) ? null : $nearest;
        $now ??= time();

        if ($firstValue === null && $nearestValue === null) {
            if (!preg_match('/^\s*(\d+)\s*(?:days?|روز)?\s*$/iu', $relative, $m)) {
                throw new RuntimeException('IBSng account expiry state could not be verified.');
            }
            return ['user_id' => $userId, 'expired' => false, 'remaining_days' => (int)$m[1], 'first_login' => null, 'nearest_exp_date' => null];
        }
        if ($nearestValue === null) {
            throw new RuntimeException('IBSng nearest expiration date is missing; renewal stopped safely.');
        }
        $expiry = strtotime($nearestValue);
        if ($expiry === false) {
            throw new RuntimeException('IBSng nearest expiration date could not be parsed; renewal stopped safely.');
        }
        $seconds = $expiry - $now;
        return [
            'user_id' => $userId,
            'expired' => $seconds <= 0,
            'remaining_days' => $seconds <= 0 ? 0 : (int)ceil($seconds / 86400),
            'first_login' => $firstValue,
            'nearest_exp_date' => $nearestValue,
        ];
    }

    private static function isEmptyValue(string $value, array $knownEmpty): bool
    {
        return in_array($value, $knownEmpty, true) || ($value !== '' && preg_match('/^-+$/', $value) === 1);
    }

    private static function displayedLabelValue(string $html, string $wanted): ?string
    {
        [$dom, $xpath] = self::dom($html);
        $cells = $xpath->query('//td');
        if (!$cells) return null;
        foreach ($cells as $cell) {
            if (!$cell instanceof DOMElement || strcasecmp(trim(self::text($cell->textContent), ' :'), $wanted) !== 0) continue;
            $row = $cell->parentNode;
            if (!$row instanceof DOMElement || strtolower($row->tagName) !== 'tr') continue;
            $seen = false;
            foreach ($row->childNodes as $sibling) {
                if (!$sibling instanceof DOMElement || strtolower($sibling->tagName) !== 'td') continue;
                if ($sibling->isSameNode($cell)) { $seen = true; continue; }
                if (!$seen) continue;
                $class = $sibling->getAttribute('class');
                if (!str_contains($class, 'Form_Content_Row_Right') && !str_contains($class, 'Form_Content_Row_Right_Textarea')) continue;
                return self::cellValue($xpath, $sibling);
            }
        }
        return null;
    }

    private static function attributeValue(string $html, string $name): ?string
    {
        [$dom, $xpath] = self::dom($html);
        $inputs = $xpath->query('//input[starts-with(@name, "attr_edit_checkbox_")]');
        if (!$inputs) return null;
        foreach ($inputs as $checkbox) {
            if (!$checkbox instanceof DOMElement || trim($checkbox->getAttribute('value')) !== $name) continue;
            $rows = $xpath->query('ancestor::tr', $checkbox);
            if (!$rows || $rows->length === 0) continue;
            $best = null; $bestScore = -1;
            foreach ($rows as $row) {
                $cells = $xpath->query('./td', $row);
                if (!$cells || $cells->length < 2) continue;
                $index = -1; $labelCell = null;
                foreach ($cells as $i => $cell) {
                    $matches = $xpath->query('.//input[@name="' . $checkbox->getAttribute('name') . '"]', $cell);
                    if ($matches && $matches->length) { $index = (int)$i; $labelCell = $cell; break; }
                }
                if ($index < 0 || !$labelCell) continue;
                for ($i = $index + 1; $i < $cells->length; $i++) {
                    $cell = $cells->item($i);
                    $class = $cell instanceof DOMElement ? $cell->getAttribute('class') : '';
                    if (!str_contains($class, 'Form_Content_Row_Right') && !str_contains($class, 'Form_Content_Row_Right_Textarea')) continue;
                    $score = str_contains($labelCell->getAttribute('class'), 'Form_Content_Row_Left') ? 120 : 100;
                    if ($score > $bestScore) { $best = $cell; $bestScore = $score; }
                    break;
                }
            }
            return $best instanceof DOMElement ? self::cellValue($xpath, $best) : null;
        }
        return null;
    }

    private static function cellValue(DOMXPath $xpath, DOMElement $cell): string
    {
        $textarea = $xpath->query('.//textarea', $cell);
        if ($textarea && $textarea->length) return self::text($textarea->item(0)->textContent);
        $select = $xpath->query('.//select', $cell);
        if ($select && $select->length) {
            $selected = $xpath->query('.//option[@selected]', $select->item(0));
            return self::text(($selected && $selected->length ? $selected->item(0) : $select->item(0))->textContent);
        }
        $input = $xpath->query('.//input[not(@type="checkbox") and not(@type="hidden")]', $cell);
        if ($input && $input->length && $input->item(0) instanceof DOMElement) return self::text($input->item(0)->getAttribute('value'));
        return self::text($cell->textContent);
    }

    private static function dom(string $html): array
    {
        if (!class_exists('DOMDocument')) throw new RuntimeException('PHP DOM extension is required to parse IBSng A1.24 user information.');
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            return [$dom, new DOMXPath($dom)];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function text(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
