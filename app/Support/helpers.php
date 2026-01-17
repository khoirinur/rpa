<?php

use Illuminate\Support\Facades\Log;

if (! function_exists('sanitize_decimal')) {
    function sanitize_decimal(mixed $value, int $maxFractionDigits = 3, ?string $context = null): float
    {
        $originalValue = $value;

        if ($value === null || $value === '') {
            sanitize_decimal_log_debug($context, $originalValue, '0', 0.0);

            return 0.0;
        }

        if (is_numeric($value) && ! is_string($value)) {
            $sanitized = round((float) $value, $maxFractionDigits);
            sanitize_decimal_log_debug($context, $originalValue, (string) $value, $sanitized);

            return $sanitized;
        }

        $numericString = preg_replace('/[^0-9,.-]/', '', (string) $value) ?? '';

        if ($numericString === '' || $numericString === '-' || $numericString === ',' || $numericString === '.') {
            sanitize_decimal_log_debug($context, $originalValue, '0', 0.0);

            return 0.0;
        }

        $sign = str_starts_with($numericString, '-') ? -1 : 1;
        $numericString = ltrim($numericString, '-');

        $lastDot = strrpos($numericString, '.');
        $lastComma = strrpos($numericString, ',');
        $decimalSeparator = null;

        if ($lastDot !== false && $lastComma !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            $decimalSeparator = ',';
        } elseif ($lastDot !== false && sanitize_decimal_should_treat_dot_as_decimal($numericString, $maxFractionDigits)) {
            $decimalSeparator = '.';
        }

        if ($decimalSeparator !== null) {
            $decimalPosition = $decimalSeparator === '.' ? $lastDot : $lastComma;
            $integerPart = substr($numericString, 0, $decimalPosition);
            $fractionalPart = substr($numericString, $decimalPosition + 1);

            $integerDigits = preg_replace('/[^0-9]/', '', $integerPart) ?? '';
            $fractionalDigits = preg_replace('/[^0-9]/', '', $fractionalPart) ?? '';
            $fractionalDigits = substr($fractionalDigits, 0, $maxFractionDigits);

            $normalized = ($integerDigits === '' ? '0' : $integerDigits)
                . ($fractionalDigits === '' ? '' : '.' . $fractionalDigits);
        } else {
            $normalized = preg_replace('/[^0-9]/', '', $numericString) ?? '';
        }

        if ($normalized === '' || $normalized === '.') {
            sanitize_decimal_log_debug($context, $originalValue, '0', 0.0);

            return 0.0;
        }

        $sanitized = $sign * (float) $normalized;
        $sanitized = round($sanitized, $maxFractionDigits);

        sanitize_decimal_log_debug($context, $originalValue, $normalized, $sanitized);

        return $sanitized;
    }
}

if (! function_exists('sanitize_positive_decimal')) {
    function sanitize_positive_decimal(mixed $value, int $maxFractionDigits = 3, ?string $context = null): float
    {
        return max(sanitize_decimal($value, $maxFractionDigits, $context), 0.0);
    }
}

if (! function_exists('sanitize_rupiah')) {
    function sanitize_rupiah(mixed $value, bool $allowNegative = false, ?string $context = null): float
    {
        $originalValue = $value;

        if ($value === null || $value === '') {
            sanitize_decimal_log_debug($context ?? 'rupiah', $originalValue, '0', 0.0);

            return 0.0;
        }

        if (is_numeric($value) && ! is_string($value)) {
            $amount = (float) $value;
        } else {
            $numericString = preg_replace('/[^0-9-]/', '', (string) $value) ?? '';

            if ($numericString === '' || $numericString === '-') {
                $amount = 0.0;
            } else {
                $sign = str_starts_with($numericString, '-') ? -1 : 1;
                $numericString = ltrim($numericString, '-');
                $amount = $numericString === '' ? 0.0 : (float) $numericString;
                $amount *= $sign;
            }
        }

        if (! $allowNegative) {
            $amount = max($amount, 0.0);
        }

        // Batasi maksimal sesuai tipe database decimal(18,2)
        $maxAmount = 9999999999999999.99;
        if ($amount > $maxAmount) {
            $amount = $maxAmount;
        }

        sanitize_decimal_log_debug($context ?? 'rupiah', $originalValue, (string) $amount, $amount);

        return $amount;
    }
}

if (! function_exists('sanitize_decimal_should_treat_dot_as_decimal')) {
    function sanitize_decimal_should_treat_dot_as_decimal(string $numericString, int $maxFractionDigits): bool
    {
        $dotCount = substr_count($numericString, '.');

        if ($dotCount === 0 || $dotCount > 1) {
            return false;
        }

        $lastDot = strrpos($numericString, '.');

        if ($lastDot === false) {
            return false;
        }

        $fractionalDigits = substr($numericString, $lastDot + 1);
        $fractionalDigits = strlen(preg_replace('/[^0-9]/', '', $fractionalDigits) ?? '');

        if ($fractionalDigits === 0 || $fractionalDigits > $maxFractionDigits) {
            return false;
        }

        $integerDigits = substr($numericString, 0, $lastDot);
        $integerDigits = strlen(preg_replace('/[^0-9]/', '', $integerDigits) ?? '');

        if ($fractionalDigits === 3 && $integerDigits <= 3) {
            return false;
        }

        return true;
    }
}

if (! function_exists('sanitize_decimal_log_debug')) {
    function sanitize_decimal_log_debug(?string $context, mixed $originalValue, string $normalizedValue, float $resultValue): void
    {
        if (! function_exists('app') || ! function_exists('config')) {
            return;
        }

        if (! config('app.debug')) {
            return;
        }

        if (! class_exists(Log::class) || ! app()->bound('log')) {
            return;
        }

        try {
            // Log::debug('numeric_sanitizer.decimal', [
            //     'context' => $context,
            //     'original_value' => $originalValue,
            //     'normalized_value' => $normalizedValue,
            //     'result_value' => $resultValue,
            // ]);
        } catch (Throwable $exception) {
            // Swallow logging exceptions to avoid interrupting execution when Log is not fully booted.
        }
    }
}

if (! function_exists('normalize_item_name')) {
    function normalize_item_name(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;
        $stringValue = str_replace(["\u{00A0}", "\xC2\xA0"], ' ', $stringValue);
        $stringValue = preg_replace('/\s+/u', ' ', $stringValue) ?? '';
        $stringValue = trim($stringValue);

        return $stringValue === '' ? null : $stringValue;
    }
}
