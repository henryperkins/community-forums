<?php

declare(strict_types=1);

namespace App\Support;

/** A display hint only: user agents are self-reported, never authority. */
final class UserAgentLabel
{
    public static function for(?string $userAgent): string
    {
        // Bound work even for malformed legacy rows or direct callers. Session
        // persistence already truncates the stored header; never infer a model.
        $agent = substr($userAgent ?? '', 0, 1024);
        $browser = null;
        foreach ([
            'Edge' => '~\b(?:Edg|Edge|EdgA|EdgiOS)/\d~i',
            'Opera' => '~\b(?:OPR|Opera|OPiOS)/\d~i',
            'Samsung Internet' => '~\bSamsungBrowser/\d~i',
            'Firefox' => '~\b(?:Firefox|FxiOS)/\d~i',
            'Chrome' => '~\b(?:Chrome|CriOS)/\d~i',
            'Safari' => '~\bVersion/\d.*\bSafari/\d~i',
        ] as $name => $pattern) {
            if (preg_match($pattern, $agent) === 1) {
                $browser = $name;
                break;
            }
        }
        $os = null;
        foreach ([
            'iOS' => '~\b(?:iPhone|iPad|iPod)\b~i',
            'Android' => '~\bAndroid\b~i',
            'Windows' => '~\bWindows (?:NT|Phone)\b~i',
            'macOS' => '~\b(?:Macintosh|Mac OS X)\b~i',
            'Linux' => '~\bLinux\b~i',
        ] as $name => $pattern) {
            if (preg_match($pattern, $agent) === 1) {
                $os = $name;
                break;
            }
        }
        if ($browser === null && $os === null) {
            return 'Unknown device';
        }
        return ($browser ?? 'Unknown browser') . ' on ' . ($os ?? 'unknown OS');
    }
}
