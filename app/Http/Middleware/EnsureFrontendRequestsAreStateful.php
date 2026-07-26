<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumMiddleware;

/**
 * Sanctum forces SameSite=lax in configureSecureCookieSessions(), which breaks
 * credentialed cross-origin SPAs (e.g. wedding-invitation.test → wedding-invitation-api.test).
 * Keep session.same_site from config (.env) so CSRF + session cookies align.
 */
class EnsureFrontendRequestsAreStateful extends SanctumMiddleware
{
    protected function configureSecureCookieSessions(): void
    {
        config([
            'session.http_only' => true,
            'session.same_site' => config('session.same_site', 'lax'),
        ]);
    }
}
