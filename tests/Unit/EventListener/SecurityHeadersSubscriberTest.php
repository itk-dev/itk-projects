<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\SecurityHeadersSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriberTest extends TestCase
{
    private const BASE_CSP = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public function testSubscribesToKernelResponse(): void
    {
        self::assertArrayHasKey(KernelEvents::RESPONSE, SecurityHeadersSubscriber::getSubscribedEvents());
    }

    public function testSetsHardeningHeadersAndCspOutsideDebug(): void
    {
        $response = $this->handle(new SecurityHeadersSubscriber(debug: false), HttpKernelInterface::MAIN_REQUEST);

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertSame(self::BASE_CSP, $response->headers->get('Content-Security-Policy'));
    }

    public function testOmitsCspInDebug(): void
    {
        $response = $this->handle(new SecurityHeadersSubscriber(debug: true), HttpKernelInterface::MAIN_REQUEST);

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testIgnoresSubRequests(): void
    {
        $response = $this->handle(new SecurityHeadersSubscriber(debug: false), HttpKernelInterface::SUB_REQUEST);

        self::assertFalse($response->headers->has('X-Content-Type-Options'));
    }

    public function testAllowsTheFeedbackWidgetOriginAsScriptAndConnectSource(): void
    {
        $subscriber = new SecurityHeadersSubscriber(
            debug: false,
            feedbackWidgetUrl: 'https://feedback.example.com/build/widget/widget.js?v=2',
        );

        $csp = (string) $this->handle($subscriber, HttpKernelInterface::MAIN_REQUEST)->headers->get('Content-Security-Policy');

        self::assertStringContainsString("script-src 'self' 'unsafe-inline' https://feedback.example.com;", $csp);
        self::assertStringContainsString("connect-src 'self' https://feedback.example.com", $csp);
        self::assertStringNotContainsString('/build/widget', $csp);
    }

    public function testKeepsAnExplicitPortInTheWidgetOrigin(): void
    {
        $subscriber = new SecurityHeadersSubscriber(
            debug: false,
            feedbackWidgetUrl: 'http://localhost:8080/build/widget/widget.js',
        );

        $csp = (string) $this->handle($subscriber, HttpKernelInterface::MAIN_REQUEST)->headers->get('Content-Security-Policy');

        self::assertStringContainsString("script-src 'self' 'unsafe-inline' http://localhost:8080;", $csp);
        self::assertStringContainsString("connect-src 'self' http://localhost:8080", $csp);
    }

    /** @return iterable<string, array{string}> */
    public static function provideUnusableWidgetUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['/build/widget/widget.js'];
        yield 'not http' => ['ftp://feedback.example.com/widget.js'];
        yield 'unparsable' => ['http:///widget.js'];
    }

    #[DataProvider('provideUnusableWidgetUrls')]
    public function testLeavesThePolicyUnchangedWithoutAUsableWidgetUrl(string $url): void
    {
        $subscriber = new SecurityHeadersSubscriber(debug: false, feedbackWidgetUrl: $url);

        $response = $this->handle($subscriber, HttpKernelInterface::MAIN_REQUEST);

        self::assertSame(self::BASE_CSP, $response->headers->get('Content-Security-Policy'));
    }

    private function handle(SecurityHeadersSubscriber $subscriber, int $requestType): Response
    {
        $response = new Response();
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), new Request(), $requestType, $response);

        $subscriber->onKernelResponse($event);

        return $response;
    }
}
