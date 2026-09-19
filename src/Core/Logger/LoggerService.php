<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Logger;

use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIconDeps\Psr\Log\AbstractLogger;
use JooosiIconDeps\Psr\Log\LogLevel;
use Stringable;
use Throwable;
/**
 * PSR-3 compatible logger service for JooosiIcon plugin
 * 
 * Provides centralized logging with support for different log levels,
 * context data, exception handling, and WordPress debug mode awareness.
 * 
 * @since 1.0.0
 * 
 * @example
 * // Basic usage
 * $logger->info('Icon loaded', ['icon' => 'mdi:home']);
 * 
 * // With exception
 * $logger->error('Failed to fetch icon', [
 *     'component' => LogComponent::ICON_SERVICE,
 *     'exception' => $e
 * ]);
 * 
 * // With component context
 * $logger->debug('Cache hit', [
 *     'component' => LogComponent::ICON_SERVICE,
 *     'key' => 'icon:123'
 * ]);
 */
#[Service]
final class LoggerService extends AbstractLogger
{
    private bool $enabled;
    private string $prefix;
    public function __construct()
    {
        $enabled = apply_filters('jooosi-icon/service/logger:enabled', defined('WP_DEBUG') && \WP_DEBUG);
        $this->enabled = (bool) apply_filters_deprecated('omni-icon/service/logger:enabled', [$enabled], JOOOSI_ICON::VERSION, 'jooosi-icon/service/logger:enabled');
        $this->prefix = 'JooosiIcon';
    }
    /**
     * Logs with an arbitrary level
     * 
     * @param mixed $level PSR-3 log level
     * @param string|Stringable $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        $formattedMessage = $this->formatMessage($level, (string) $message, $context);
        error_log($formattedMessage);
    }
    /**
     * Format log message with level, context, and exception details
     * 
     * @param mixed $level PSR-3 log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     * @return string Formatted log message
     */
    private function formatMessage($level, string $message, array $context): string
    {
        $levelStr = is_string($level) ? strtoupper($level) : 'INFO';
        $parts = ["[{$this->prefix}]", "[{$levelStr}]"];
        // Add component prefix if provided
        if (isset($context['component'])) {
            $component = $context['component'];
            if ($component instanceof \JooosiIcon\Core\Logger\LogComponent) {
                $parts[] = '[' . $component->value . ']';
            } elseif (is_string($component)) {
                $parts[] = '[' . $component . ']';
            }
        }
        $parts[] = $message;
        // Add exception details if provided
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            $parts[] = sprintf('| Exception: %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine());
            // Add stack trace for ERROR and CRITICAL levels
            if ($level === LogLevel::ERROR || $level === LogLevel::CRITICAL) {
                $parts[] = '| Stack trace: ' . $exception->getTraceAsString();
            }
        }
        // Add additional context data
        $contextData = array_filter($context, function ($key) {
            return !in_array($key, ['component', 'exception'], \true);
        }, \ARRAY_FILTER_USE_KEY);
        if (!empty($contextData)) {
            $parts[] = '| Context: ' . wp_json_encode($contextData);
        }
        return implode(' ', $parts);
    }
    /**
     * Check if logging is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
