<?php

namespace Fnp\ElStart\Helpers;


class LogFmt
{
    public static function msg(string $message, ...$context): string
    {
        $params = [];

        foreach ($context as $index => $value) {
            if (str_contains($message, '%' . ($index + 1))) {
                $message = str_replace('%' . ($index + 1), $value, $message);
            } else {
                $params[] = $value;
            }
        }

        return implode(' ', [
            self::caller(...$params),
            $message,
        ]);
    }

    public static function err(\Exception $e): string
    {
        return implode(' ', [
            self::caller(),
            sprintf('[!] Exception: %s on line %d in file %s with message: %s',
                    get_class($e), $e->getLine(),
                    $e->getFile(), $e->getMessage(),
            ),
        ]);
    }

    public static function start(): float
    {
        return microtime(true);
    }

    public static function stop(float $start): string
    {
        $duration = microtime(true) - $start;
        return date('i:s', round($duration));
    }

    protected static function caller(...$params): string
    {
        $backTrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5);
        $caller    = array_shift($backTrace);

        while (isset($caller['class']) && $caller['class'] == LogFmt::class && !empty($backTrace)) {
            $caller = array_shift($backTrace);
        }

        $class = $caller['class'] ?? 'none';
        $class = explode('\\', $class);
        $class = array_reverse([array_pop($class), array_pop($class)]);
        $class = implode('\\', $class);


        $func = $caller['function'] ?? null;

        if ($func) {
            return '<' . $class . '::' . $func . '(' . implode(', ', $params) . ')>';
        }

        return '<' . $class . '>';
    }
}
