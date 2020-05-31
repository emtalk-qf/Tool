<?php
namespace App\Http\Sdk\Traits;
use App\Helper\Unit\SlogUnit;

/**
 * SDK LOG日志处理类
 *
 * @version  1.0.0
 * @author   Yunuo <ciwdream@gmail.com>
 */
trait Log
{

    protected function logger($message,$type='info')
    {
        self::loggers($message,$type);
        return;
    }

    protected static function loggers($message,$type='info')
    {
        SlogUnit::slog($message,$type);
        $_type = strtolower($type);
        \Illuminate\Support\Facades\Log::$_type($message);
        return;
    }

}
