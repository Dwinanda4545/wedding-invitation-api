<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;

class ValidateCsrfToken extends Middleware
{
    /**
     * The framework decrypts X-XSRF-TOKEN by default. If the XSRF-TOKEN cookie
     * is not encrypted (typical: encryptCookies excepts XSRF-TOKEN) or the
     * client sends the raw session token, decryption fails and CSRF errors.
     * Fall back to the raw header value so it can be compared to the session.
     */
    protected function getTokenFromRequest($request)
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (! $token && $header = $request->header('X-XSRF-TOKEN')) {
            try {
                $token = CookieValuePrefix::remove($this->encrypter->decrypt($header, static::serialized()));
            } catch (DecryptException) {
                $token = $header;
            }
        }

        return $token;
    }
}
