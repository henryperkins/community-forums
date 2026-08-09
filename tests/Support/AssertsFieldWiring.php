<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Assertions for the server-rendered field/error wiring that {@see field_attrs}
 * and {@see field_error} emit on a 422: the offending control carries
 * aria-invalid="true" and an aria-describedby pointing at the id of the error
 * line that is actually rendered.
 *
 * Shared by the admin console suite and the member-surface suite so both hold
 * the same contract — there is no JS in this path, so the markup IS the
 * accessibility behaviour and it has to be asserted on the response body.
 */
trait AssertsFieldWiring
{
    /**
     * Assert a control is wired to its error line, and return the matched tag
     * so a caller can make further assertions about it.
     *
     * @param string      $errorId   Full id, or a prefix when the template
     *                               scopes ids per instance. Defaults to
     *                               field_error()'s own derived id.
     * @param string|null $elementId Disambiguates when several controls on the
     *                               page share a name.
     */
    protected function assertFieldWired(
        string $body,
        string $tag,
        string $field,
        ?string $errorId = null,
        ?string $elementId = null,
    ): string {
        $idAssertion = $elementId === null
            ? ''
            : '(?=[^>]*\\bid="' . preg_quote($elementId, '/') . '")';
        $matched = preg_match(
            '/<' . preg_quote($tag, '/') . '\\b'
                . '(?=[^>]*\\bname="' . preg_quote($field, '/') . '")'
                . $idAssertion
                . '[^>]*>/s',
            $body,
            $matches,
        );
        self::assertSame(1, $matched, 'Expected to find the ' . $field . ' field.');
        $element = $matches[0];
        $errorId ??= 'err-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $field);
        self::assertStringContainsString('aria-invalid="true"', $element);
        self::assertMatchesRegularExpression(
            '/aria-describedby="[^"]*' . preg_quote($errorId, '/') . '[^"]*"/',
            $element,
        );

        return $element;
    }

    /**
     * Same, scoped to one form on a page that renders several — the case that
     * makes an unscoped error id collide.
     */
    protected function assertFieldWiredInForm(
        string $body,
        string $action,
        string $tag,
        string $field,
        string $errorId,
    ): void {
        $matched = preg_match(
            '/<form\b(?=[^>]*\baction="' . preg_quote($action, '/') . '")[^>]*>(?<form>.*?)<\/form>/s',
            $body,
            $matches,
        );
        self::assertSame(1, $matched, 'Expected to find form action ' . $action . '.');
        $this->assertFieldWired((string) $matches['form'], $tag, $field, $errorId);
        self::assertStringContainsString('id="' . $errorId . '"', $body);
    }
}
