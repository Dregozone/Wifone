<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets Wifone be used, signed in, inside a frame on the sites listed in
 * wifone.embed.origins (the Life OS game keeps it open so calls reach you
 * wherever you are in it), without loosening anything for normal visits.
 *
 * A framed page is a third party to the page around it, and browsers refuse
 * its Lax session cookie. So a framed request (the frame's own navigation, or
 * anything already carrying the embedded cookie) gets a session of its own,
 * under a different cookie name, with every cookie on the response made
 * SameSite=None, Secure and Partitioned. A partitioned cookie is kept per
 * top-level site: the sign-in made inside the game only exists inside the
 * game, and another site framing Wifone would find an empty jar. Visiting
 * Wifone directly never sees that cookie and keeps the usual Lax session.
 *
 * Every response also says who may frame it at all: this site and the listed
 * origins, nobody else.
 *
 * The same as Calm Tube's (calm-tube/app/Http/Middleware/EmbeddedSession.php).
 */
class EmbeddedSession
{
    public const COOKIE = 'wifone-embed';

    public function handle(Request $request, Closure $next): Response
    {
        $embedded = $request->headers->get('Sec-Fetch-Dest') === 'iframe' || $request->cookies->has(self::COOKIE);

        if ($embedded) {
            config([
                'session.cookie' => self::COOKIE,
                'session.secure' => true,
                'session.same_site' => 'none',
                'session.partitioned' => true,
            ]);
            // The store takes its cookie name when it is built; it may already have been.
            app('session')->driver()->setName(self::COOKIE);
        }

        $response = $next($request);

        if ($embedded) {
            // The session and CSRF cookies follow the config above; the
            // remember-me cookie comes from the cookie jar, which cannot make a
            // partitioned cookie. Rewrite them all the same way.
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
                $response->headers->setCookie($cookie->withSecure(true)->withSameSite(Cookie::SAMESITE_NONE)->withPartitioned(true));
            }
        }

        $response->headers->set('Content-Security-Policy', 'frame-ancestors '.implode(' ', ["'self'", ...config('wifone.embed.origins')]));

        return $response;
    }
}
