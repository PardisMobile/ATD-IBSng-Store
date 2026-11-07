<?php
declare(strict_types=1);

/**
 * Strict read-only normalization of IBSng A1.24 account expiration data.
 * Uses a pure-PHP HTML reader so customer hosting does not require ext-dom.
 */
final class IBSngRenewalState
{
    public static function normalizeUtcOffset(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        if (!preg_match('/^([+-]?)([0-9]{4})$/D', $value, $match)) {
            throw new InvalidArgumentException('IBSng UTC offset must contain four English digits with an optional sign.');
        }
        $hours = (int)substr($match[2], 0, 2);
        $minutes = (int)substr($match[2], 2, 2);
        if ($minutes > 59 || $hours > 14 || ($hours === 14 && $minutes !== 0)) {
            throw new InvalidArgumentException('IBSng UTC offset is outside the supported range.');
        }
        return ($match[1] === '-' ? '-' : '+') . $match[2];
    }

    private static function offsetDateTimeZone(?string $utcOffset): DateTimeZone
    {
        $normalized = self::normalizeUtcOffset($utcOffset);
        if ($normalized === null) return new DateTimeZone('UTC');
        return new DateTimeZone(substr($normalized, 0, 3) . ':' . substr($normalized, 3, 2));
    }

    public static function fromClient(IBSngClient $client, string $username, ?string $utcOffset = null): array
    {
        $user = $client->lookupUser($username);
        $html = (string)($user['html'] ?? '');
        if ($html === '') {
            throw new RuntimeException('IBSng user information markup was not available.');
        }
        $firstLogin = self::attributeValue($html, 'first_login');
        $relativeExpiration = self::attributeValue($html, 'rel_exp');
        $nearestExpiration = self::displayedLabelValue($html, 'Nearest Expiration Date');
        $state = self::normalize((string)$user['user_id'], $firstLogin, $relativeExpiration, $nearestExpiration, null, $utcOffset);
        $state['group'] = trim((string)($user['group'] ?? ''));
        if ($state['group'] === '') {
            throw new RuntimeException('IBSng account group could not be verified; renewal stopped safely.');
        }
        return $state;
    }

    public static function normalize(string $userId, ?string $firstLogin, ?string $relativeExpiration, ?string $nearestExpiration, ?int $now = null, ?string $utcOffset = null): array
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
        try {
            // IBSng displays a wall-clock date in its own configured UTC offset.
            $expiryDate = new DateTimeImmutable($nearestValue, self::offsetDateTimeZone($utcOffset));
            $expiry = $expiryDate->getTimestamp();
        } catch (Throwable $parseError) {
            throw new RuntimeException('IBSng nearest expiration date could not be parsed; renewal stopped safely.', 0, $parseError);
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

    /** Parse HTML attributes without DOM/libxml. */
    private static function attributes(string $tag): array
    {
        $attrs = [];
        if (preg_match_all('/([a-zA-Z_:][a-zA-Z0-9_:.-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/s', $tag, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $attribute) {
                $value = $attribute[2] !== '' ? $attribute[2] : ($attribute[3] !== '' ? $attribute[3] : ($attribute[4] ?? ''));
                $attrs[strtolower($attribute[1])] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return $attrs;
    }

    /** Return HTML tag tokens with byte offsets. */
    private static function tokens(string $html): array
    {
        preg_match_all('/<!--.*?-->|<![^>]*>|<\?[^>]*\?>|<\/?[a-zA-Z][^>]*>/s', $html, $matches, PREG_OFFSET_CAPTURE);
        return $matches[0] ?? [];
    }

    /** Extract balanced elements of a given tag, preserving their original markup. */
    private static function elementBlocks(string $html, string $wantedTag): array
    {
        $stack = [];
        $blocks = [];
        foreach (self::tokens($html) as [$token, $offset]) {
            if (!preg_match('/^<\s*(\/?)\s*([a-zA-Z0-9]+)/', $token, $match)) continue;
            $closing = $match[1] === '/';
            $tag = strtolower($match[2]);
            if ($closing) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['tag'] !== $tag) continue;
                    $opened = $stack[$i];
                    if ($tag === strtolower($wantedTag)) {
                        $end = $offset + strlen($token);
                        $blocks[] = [
                            'attrs' => $opened['attrs'],
                            'inner' => substr($html, $opened['inner_start'], $offset - $opened['inner_start']),
                            'outer' => substr($html, $opened['start'], $end - $opened['start']),
                        ];
                    }
                    $stack = array_slice($stack, 0, $i);
                    break;
                }
                continue;
            }
            $attrs = self::attributes($token);
            $void = preg_match('/\/\s*>$/', $token) || in_array($tag, ['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'], true);
            if (!$void) $stack[] = ['tag'=>$tag, 'attrs'=>$attrs, 'start'=>$offset, 'inner_start'=>$offset + strlen($token)];
        }
        return $blocks;
    }

    /** Extract only direct TD children from each row, not nested-table cells. */
    private static function directCells(string $rowHtml): array
    {
        $tokens = self::tokens($rowHtml);
        $stack = [];
        $cells = [];
        foreach ($tokens as [$token, $offset]) {
            if (!preg_match('/^<\s*(\/?)\s*([a-zA-Z0-9]+)/', $token, $match)) continue;
            $closing = $match[1] === '/';
            $tag = strtolower($match[2]);
            if ($closing) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['tag'] !== $tag) continue;
                    $opened = $stack[$i];
                    if ($tag === 'td' && $opened['direct']) {
                        $cells[] = [
                            'attrs' => $opened['attrs'],
                            'html' => substr($rowHtml, $opened['inner_start'], $offset - $opened['inner_start']),
                        ];
                    }
                    $stack = array_slice($stack, 0, $i);
                    break;
                }
                continue;
            }
            $attrs = self::attributes($token);
            $nearestTr = null;
            foreach (array_reverse($stack) as $ancestor) {
                if ($ancestor['tag'] === 'tr') { $nearestTr = $ancestor; break; }
            }
            $direct = $tag === 'td' && $nearestTr !== null && (($nearestTr['depth'] + 1) === count($stack));
            $void = preg_match('/\/\s*>$/', $token) || in_array($tag, ['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'], true);
            if (!$void) $stack[] = ['tag'=>$tag, 'attrs'=>$attrs, 'inner_start'=>$offset + strlen($token), 'direct'=>$direct, 'depth'=>count($stack)];
        }
        return $cells;
    }

    private static function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private static function controlValue(string $cellHtml): string
    {
        if (preg_match('/<textarea\b[^>]*>(.*?)<\/textarea\s*>/is', $cellHtml, $match)) {
            return self::cleanText($match[1]);
        }
        if (preg_match('/<select\b[^>]*>(.*?)<\/select\s*>/is', $cellHtml, $match)) {
            if (preg_match('/<option\b[^>]*\bselected\b[^>]*>(.*?)<\/option\s*>/is', $match[1], $option)) return self::cleanText($option[1]);
            if (preg_match('/<option\b[^>]*>(.*?)<\/option\s*>/is', $match[1], $option)) return self::cleanText($option[1]);
            return self::cleanText($match[1]);
        }
        if (preg_match('/<input\b(?![^>]*\btype\s*=\s*["\']?(?:checkbox|hidden))[^>]*>/is', $cellHtml, $match)) {
            $attrs = self::attributes($match[0]);
            return trim((string)($attrs['value'] ?? ''));
        }
        return self::cleanText($cellHtml);
    }

    private static function displayedLabelValue(string $html, string $wanted): ?string
    {
        foreach (self::elementBlocks($html, 'tr') as $row) {
            $cells = self::directCells($row['outer']);
            for ($i = 0; $i < count($cells); $i++) {
                $label = trim(self::cleanText($cells[$i]['html']), ' :');
                if (strcasecmp($label, $wanted) !== 0) continue;
                for ($j = $i + 1; $j < count($cells); $j++) {
                    $class = $cells[$j]['attrs']['class'] ?? '';
                    if (str_contains($class, 'Form_Content_Row_Right') || str_contains($class, 'Form_Content_Row_Right_Textarea')) {
                        return self::controlValue($cells[$j]['html']);
                    }
                }
            }
        }
        return null;
    }

    private static function attributeValue(string $html, string $wanted): ?string
    {
        foreach (self::elementBlocks($html, 'tr') as $row) {
            $cells = self::directCells($row['outer']);
            $checkboxCell = -1;
            foreach ($cells as $i => $cell) {
                if (!preg_match_all('/<input\b[^>]*>/is', $cell['html'], $inputs)) continue;
                foreach ($inputs[0] as $inputTag) {
                    $attrs = self::attributes($inputTag);
                    if (str_starts_with(strtolower((string)($attrs['name'] ?? '')), 'attr_edit_checkbox_')
                        && trim((string)($attrs['value'] ?? '')) === $wanted) {
                        $checkboxCell = $i;
                        break 2;
                    }
                }
            }
            if ($checkboxCell < 0) continue;
            for ($j = $checkboxCell + 1; $j < count($cells); $j++) {
                $class = $cells[$j]['attrs']['class'] ?? '';
                if (str_contains($class, 'Form_Content_Row_Right') || str_contains($class, 'Form_Content_Row_Right_Textarea')) {
                    return self::controlValue($cells[$j]['html']);
                }
            }
        }
        return null;
    }
}
