<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * PSR-3 compatible logger for Discovery system
 * 
 * Logs to WordPress error_log with structured format and context
 * 
 * @since 1.0.0
 */
final class DiscoveryLogger extends AbstractLogger
{
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Logs with an arbitrary level
     * 
     * @param mixed $level
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $formattedMessage = $this->formatMessage($level, (string) $message, $context);
        error_log($formattedMessage);
    }

    /**
     * Format log message with level, context, and exception details
     * 
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    private function formatMessage($level, string $message, array $context): string
    {
        $levelStr = is_string($level) ? strtoupper($level) : 'INFO';
        $parts = ['[JooosiIcon Discovery]', "[$levelStr]"];

        // Add context prefix if provided
        if (isset($context['component']) && is_string($context['component'])) {
            $parts[] = '[' . $context['component'] . ']';
        }

        $parts[] = $message;

        // Add exception details if provided
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            $parts[] = sprintf(
                '| Exception: %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );

            // Add stack trace for ERROR level
            if ($level === LogLevel::ERROR || $level === LogLevel::CRITICAL) {
                $parts[] = '| Stack trace: ' . $exception->getTraceAsString();
            }
        }

        // Add additional context data
        $contextData = array_filter($context, function ($key) {
            return ! in_array($key, ['component', 'exception'], true);
        }, ARRAY_FILTER_USE_KEY);

        if (! empty($contextData)) {
            $parts[] = '| Context: ' . wp_json_encode($contextData);
        }

        return implode(' ', $parts);
    }
}
