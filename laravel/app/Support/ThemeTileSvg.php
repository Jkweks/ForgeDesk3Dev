<?php

namespace App\Support;

/**
 * Preview thumbnails for the Customize panel (partials/theme-settings), ported from
 * the tabler.io demo's ThemeSettings component. Each tile is an inline SVG drawn on
 * Tabler's own custom properties, so it follows the colour mode, gray base and accent.
 * All share one 96x64 frame. Input is limited to the fixed option values in the
 * partial, never user data.
 */
class ThemeTileSvg
{
    private const PAGE = 'var(--tblr-bg-surface-secondary)';

    private const SURFACE = 'var(--tblr-bg-surface)';

    private const BORDER = 'var(--tblr-border-color)';

    private const PRIMARY = 'var(--tblr-primary)';

    private const ON_ACCENT = 'rgba(255, 255, 255, 0.7)';

    private const SHADE = ['light' => 'var(--tblr-gray-50)', 'dark' => 'var(--tblr-gray-900)'];

    private const LINE_ON = ['light' => 'var(--tblr-gray-300)', 'dark' => 'var(--tblr-gray-700)'];

    private const BAR = ['light' => 'var(--tblr-white)', 'dark' => 'var(--tblr-gray-800)', 'primary' => 'var(--tblr-primary)'];

    /** Tile for one value of one setting (see partials/theme-settings). */
    public static function for(string $key, string $value): string
    {
        return match ($key) {
            'theme' => self::colorMode($value),
            'navbar-position' => $value === 'vertical'
                ? self::svg(self::frame(self::PAGE).self::rail(30, self::PRIMARY).self::lines(7, 10, [16, 16, 16, 16], self::ON_ACCENT, 10).self::lines(40, 12, [40, 48, 28], self::BORDER).self::outline())
                : self::svg(self::frame(self::PAGE).self::topBar(self::PRIMARY).self::navMarks().self::lines(12, 30, [46, 72, 30], self::BORDER).self::outline()),
            'navbar-theme' => self::svg(self::frame(self::PAGE).self::topBar(match ($value) {
                'dark' => self::BAR['dark'], 'primary' => self::BAR['primary'], default => self::BAR['light'],
            }).self::navMarks($value === 'default' ? self::BORDER : self::ON_ACCENT).self::lines(12, 30, [46, 72, 30], self::BORDER).self::outline()),
            'layout' => match ($value) {
                'boxed' => self::svg('<rect x="0.5" y="0.5" width="95" height="63" rx="6" fill="var(--tblr-bg-surface-inverted)" /><rect x="12" y="8" width="72" height="48" rx="4" fill="'.self::PAGE.'" />'.self::card(18, 16, 60, 32).self::outline()),
                'fluid' => self::svg(self::frame(self::PAGE).self::card(4, 8, 88, 48).self::outline()),
                default => self::svg(self::frame(self::PAGE).self::card(22, 8, 52, 48).self::outline()),
            },
            'navbar' => $value === 'sticky'
                ? self::svg(self::frame(self::PAGE).self::lines(12, 4, [46, 72, 30, 60], self::BORDER).self::thumb(44).'<rect x="0.5" y="19.5" width="95" height="5" fill="var(--tblr-gray-900)" opacity="0.18" />'.self::topBar(self::PRIMARY).self::outline())
                : self::svg(self::frame(self::PAGE).self::topBar(self::PRIMARY).self::lines(12, 30, [46, 72, 30], self::BORDER).self::thumb(24).self::outline()),
            'sidebar' => match ($value) {
                'folded' => self::svg(self::frame(self::PAGE).self::rail(16, self::PRIMARY).self::dots(8, 12, 4, self::ON_ACCENT).self::lines(26, 12, [50, 60, 34], self::BORDER).self::outline()),
                'folded-hover' => self::svg(self::frame(self::PAGE).self::rail(16, self::PRIMARY).self::dots(8, 12, 4, self::ON_ACCENT).self::lines(26, 12, [50, 60, 34], self::BORDER)
                    .'<rect x="16" y="0.5" width="4" height="63" fill="var(--tblr-gray-900)" opacity="0.12" /><rect x="16.5" y="0.5" width="28" height="63" fill="'.self::SURFACE.'" stroke="'.self::BORDER.'" />'
                    .self::lines(21, 9.5, [18, 18, 18, 18], self::BORDER, 11).self::outline()),
                default => self::svg(self::frame(self::PAGE).self::rail(30, self::PRIMARY).self::lines(7, 10, [16, 16, 16, 16], self::ON_ACCENT, 10).self::lines(40, 12, [40, 48, 28], self::BORDER).self::outline()),
            },
            // The base's own gray scale: the attribute swaps --tblr-gray-* for this tile only.
            'theme-base' => self::svg(
                self::frame(self::SURFACE).collect([100, 300, 500, 700, 900])->map(
                    fn ($shade, $i) => '<rect x="'.(10 + $i * 16).'" y="24" width="12" height="16" rx="3" fill="var(--tblr-gray-'.$shade.')" />'
                )->implode(''),
                ' data-bs-theme-base="'.e($value).'"'
            ),
            // One shape carries the message: a square at 0, a circle at 2.
            'theme-radius' => self::svg(self::frame(self::PAGE).'<rect x="28" y="12" width="40" height="40" rx="'.((float) $value * 10).'" fill="'.self::PRIMARY.'" />'),
            default => self::svg(self::frame(self::PAGE).self::outline()),
        };
    }

    /** Auto splits the tile into a light and a dark half; light/dark pin their own mode. */
    private static function colorMode(string $value): string
    {
        [$first, $second] = $value === 'auto' ? ['light', 'dark'] : [$value, $value];

        return self::svg(
            self::pageHalf($first, false).self::pageHalf($second, true)
            .self::barHalf(self::BAR[$first], false).self::barHalf(self::BAR[$second], true)
            .self::lines(12, 30, [46, 72, 30], self::LINE_ON[$first]).self::outline()
        );
    }

    private static function svg(string $inner, string $attrs = ''): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 64" class="form-imagecheck-image w-100" aria-hidden="true" focusable="false"'.$attrs.'>'.$inner.'</svg>';
    }

    private static function frame(string $fill): string
    {
        return '<rect x="0.5" y="0.5" width="95" height="63" rx="6" fill="'.$fill.'" stroke="'.self::BORDER.'" />';
    }

    private static function outline(): string
    {
        return '<rect x="0.5" y="0.5" width="95" height="63" rx="6" fill="none" stroke="'.self::BORDER.'" />';
    }

    private static function topBar(string $fill): string
    {
        return '<path d="M6.5 0.5h83a6 6 0 0 1 6 6v13.5h-95v-13.5a6 6 0 0 1 6 -6z" fill="'.$fill.'" /><rect x="0.5" y="19.5" width="95" height="0.5" fill="'.self::BORDER.'" />';
    }

    private static function rail(int $w, string $fill): string
    {
        $top = $w - 6;

        return '<path d="M6.5 0.5h'.$top.'v63h-'.$top.'a6 6 0 0 1 -6 -6v-51a6 6 0 0 1 6 -6z" fill="'.$fill.'" /><rect x="'.$w.'" y="0.5" width="0.5" height="63" fill="'.self::BORDER.'" />';
    }

    private static function navMarks(string $fill = self::ON_ACCENT): string
    {
        return collect([8, 26, 44])->map(fn ($x) => '<rect x="'.$x.'" y="8" width="14" height="4" rx="2" fill="'.$fill.'" />')->implode('');
    }

    /** @param  array<int, int|float>  $widths */
    private static function lines(float|int $x, float|int $y, array $widths, string $fill, int $step = 11): string
    {
        return collect($widths)->map(fn ($w, $i) => '<rect x="'.$x.'" y="'.($y + $i * $step).'" width="'.$w.'" height="5" rx="2.5" fill="'.$fill.'" />')->implode('');
    }

    private static function dots(int $x, int $y, int $count, string $fill): string
    {
        return collect(range(0, $count - 1))->map(fn ($i) => '<circle cx="'.$x.'" cy="'.($y + $i * 11).'" r="2.5" fill="'.$fill.'" />')->implode('');
    }

    private static function pageHalf(string $mode, bool $right): string
    {
        $fill = self::SHADE[$mode];

        return $right
            ? '<path d="M48 0.5h41.5a6 6 0 0 1 6 6v51a6 6 0 0 1 -6 6h-41.5z" fill="'.$fill.'" />'
            : '<path d="M6.5 0.5h41.5v63h-41.5a6 6 0 0 1 -6 -6v-51a6 6 0 0 1 6 -6z" fill="'.$fill.'" />';
    }

    private static function barHalf(string $fill, bool $right): string
    {
        return $right
            ? '<path d="M48 0.5h41.5a6 6 0 0 1 6 6v13.5h-47.5z" fill="'.$fill.'" />'
            : '<path d="M6.5 0.5h41.5v19.5h-47.5v-13.5a6 6 0 0 1 6 -6z" fill="'.$fill.'" />';
    }

    /** A content card: the page's own surface with a border, lines inside. */
    private static function card(int $x, int $y, int $w, int $h): string
    {
        return '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" rx="3" fill="'.self::SURFACE.'" stroke="'.self::BORDER.'" />'
            .self::lines($x + 6, $y + 7, array_map('round', [$w * 0.6, $w * 0.8, $w * 0.4]), self::BORDER, 9);
    }

    private static function thumb(int $y): string
    {
        return '<rect x="90" y="'.$y.'" width="3" height="14" rx="1.5" fill="'.self::BORDER.'" />';
    }
}
