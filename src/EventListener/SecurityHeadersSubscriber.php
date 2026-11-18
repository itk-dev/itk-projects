<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds baseline security headers to every response. The Content-Security-Policy
 * is emitted only outside debug mode so it cannot interfere with the Symfony
 * web profiler/debug toolbar, which injects its own inline assets.
 *
 * The feedback widget is served by the feedback collector, a different origin,
 * and posts the collected feedback to an API on that same origin. When a widget
 * URL is configured, its origin is therefore allowed as a script and connection
 * source. The widget's styles are inline and its screenshots are data: images,
 * which the baseline policy already allows.
 */
final readonly class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    /** @var array<string, list<string>> */
    private const DIRECTIVES = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'"],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:'],
        'font-src' => ["'self'"],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'none'"],
    ];

    private string $csp;

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $debug = false,
        #[Autowire('%env(string:TIDY_FEEDBACK_WIDGET_URL)%')]
        string $feedbackWidgetUrl = '',
    ) {
        $this->csp = self::buildCsp(self::origin($feedbackWidgetUrl));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (!$this->debug) {
            $headers->set('Content-Security-Policy', $this->csp);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    private static function buildCsp(?string $widgetOrigin): string
    {
        $directives = self::DIRECTIVES;

        if (null !== $widgetOrigin) {
            $directives['script-src'][] = $widgetOrigin;
            // Without an explicit connect-src, fetch() falls back to default-src,
            // which would block the widget's calls to the collector's API.
            $directives['connect-src'] = ["'self'", $widgetOrigin];
        }

        $policy = [];
        foreach ($directives as $name => $sources) {
            $policy[] = $name.' '.implode(' ', $sources);
        }

        return implode('; ', $policy);
    }

    /**
     * The scheme, host and port of an absolute http(s) URL as a CSP source
     * expression, or null when the value is empty, relative or not a URL.
     */
    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (false === $parts || !isset($parts['scheme'], $parts['host']) || !\in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
