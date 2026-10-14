<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers where a visitor first came from (an outside referrer and any
 * utm_* parameters), so the admin's sign-up email can say. Only guests'
 * page visits are looked at, and only the first one that has anything.
 */
class CaptureSignUpSource
{
    public const SESSION_KEY = 'sign_up_source';

    public const UTM_PARAMETERS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->user() === null && ! $request->session()->has(self::SESSION_KEY)) {
            $source = $this->sourceOf($request);

            if ($source !== []) {
                $request->session()->put(self::SESSION_KEY, $source);
            }
        }

        return $next($request);
    }

    /**
     * @return array<string, string>
     */
    protected function sourceOf(Request $request): array
    {
        $source = [];

        $referrer = (string) $request->headers->get('referer', '');
        $referrerHost = parse_url($referrer, PHP_URL_HOST);

        if (is_string($referrerHost) && $referrerHost !== $request->getHost()) {
            $source['referrer'] = mb_substr($referrer, 0, 500);
        }

        foreach (self::UTM_PARAMETERS as $parameter) {
            $value = $request->query($parameter);

            if (is_string($value) && $value !== '') {
                $source[$parameter] = mb_substr($value, 0, 200);
            }
        }

        return $source;
    }
}
