<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Plain-PHP template renderer with a single-level layout, named sections,
 * partials, and escaping helpers. Templates are PHP files under templates/.
 *
 * Inside a template, $this is the View, so templates call $this->e(),
 * $this->partial(), $this->layout(), $this->csrfField(), etc. Shared values
 * (site_name, current_user, csrf_token, flash, ...) and per-render data are
 * also available as local variables.
 */
final class View
{
    /** @var array<string,mixed> */
    private array $shared = [];

    /** @var array<string,string> */
    private array $sections = [];

    /** @var list<string> */
    private array $capturing = [];

    private ?string $layout = null;

    public function __construct(private string $templatePath)
    {
    }

    /** @param array<string,mixed> $data */
    public function share(array $data): void
    {
        $this->shared = $data + $this->shared;
    }

    public function shared(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    /**
     * Render a template to a complete HTML string, applying a layout if the
     * template requested one.
     *
     * @param array<string,mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $this->layout = null;
        $this->sections = [];

        $content = $this->renderTemplate($template, $data);

        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $content = $this->renderTemplate($layout, $data + ['content' => $content]);
        }

        return $content;
    }

    /** @param array<string,mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderTemplate($template, $data);
    }

    /** @param array<string,mixed> $data */
    private function renderTemplate(string $template, array $data): string
    {
        $file = $this->templatePath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Template not found: $template");
        }

        $e = fn (mixed $v): string => $this->e($v);

        $level = ob_get_level();
        ob_start();
        try {
            (function () use ($file, $data, $e): void {
                extract($this->shared, EXTR_SKIP);
                extract($data, EXTR_OVERWRITE);
                include $file;
            })();
        } catch (Throwable $ex) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $ex;
        }

        return (string) ob_get_clean();
    }

    public function layout(string $name): void
    {
        $this->layout = $name;
    }

    public function section(string $key, string $value): void
    {
        $this->sections[$key] = $value;
    }

    public function start(string $key): void
    {
        $this->capturing[] = $key;
        ob_start();
    }

    public function stop(): void
    {
        $key = array_pop($this->capturing);
        if ($key === null) {
            throw new RuntimeException('View::stop() called without start().');
        }
        $this->sections[$key] = (string) ob_get_clean();
    }

    public function block(string $key, string $default = ''): string
    {
        return $this->sections[$key] ?? $default;
    }

    /** HTML-escape any scalar for safe output. */
    public function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function csrfToken(): string
    {
        return (string) $this->shared('csrf_token', '');
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . $this->e($this->csrfToken()) . '">';
    }
}
