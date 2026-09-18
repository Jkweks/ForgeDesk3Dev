<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Smalot\PdfParser\Parser;

/**
 * Extracts structured fields from a "Quality Defect Report" PDF (the form
 * tool that generates these renders each single/multi-choice question as a
 * bulleted list of every possible option, with only the selected option(s)
 * drawn in a highlight color — everything else in the default body color.
 * That selection is invisible to plain text extraction, so this class reads
 * the page's raw content stream directly and tracks the active fill color
 * (`scn` operator) alongside each text-show (`Tj`) operator to tell them
 * apart. See docs/quality-tracking-plan.md for the field mapping this was
 * built against and a sample PDF.
 */
class QualityReportPdfExtractor
{
    /** Field labels as they appear verbatim on the form, in document order. */
    private const LABELS = [
        'date_issue_discovered' => 'Date issue discovered',
        'job' => 'Job',
        'elevation' => 'Elevation / Door Opening',
        'replacement_needed' => 'Replacement needed?',
        'problem_type' => 'Problem type',
        'description' => 'Short Description of Issue',
    ];

    /** Bottom margin excluded from parsing on every page (0.5in @ 72pt/in — the form's persistent footer band). */
    private const FOOTER_MARGIN_POINTS = 36.0;

    public function extract(string $filePath): array
    {
        $document = (new Parser)->parseFile($filePath);
        $pages = $document->getPages();

        $fullText = implode("\n", array_map(fn ($p) => $this->pageText($p), $pages));
        $rawStream = implode("\n", array_map(fn ($p) => $this->rawContentStream($p), $pages));

        // Geometrically-detected footer lines (whatever their exact wording)
        // from every page, so extractPlainFields() can skip them by content
        // even though it otherwise works off the plain (position-less) text
        // layer. This is what actually fixes "label on page N, answer on
        // page N+1" — the footer sitting between them in the concatenated
        // text no longer gets mistaken for the answer.
        $footerLines = [];
        foreach ($pages as $page) {
            $footerLines = array_merge($footerLines, $this->footerBandLines($page));
        }
        $footerLines = array_values(array_unique($footerLines));

        $plain = $this->extractPlainFields($fullText, $footerLines);
        $choices = $this->extractHighlightedChoices($rawStream);

        return array_merge($plain, $choices, [
            'raw_extracted_text' => $fullText,
        ]);
    }

    /**
     * Every distinct text fragment whose baseline falls within the bottom
     * FOOTER_MARGIN_POINTS of this page, read from the PDF's actual
     * text-positioning data (Tm matrices) rather than guessed from wording —
     * so it doesn't matter what the footer says, only where it sits.
     *
     * Deliberately returned as separate fragments rather than merged into
     * reconstructed lines: getText()'s own column-spacing heuristics glue
     * adjacent footer fragments together with inconsistent separators (a
     * literal tab between "Page 1 of 2" and "Powered by", nothing at all
     * between "Powered by" and "Downloaded on ..."), so a merged fragment
     * would rarely appear verbatim in the plain-text line to match against.
     * isFooterLine() instead strips each fragment out individually and
     * checks what's left — order- and separator-independent.
     *
     * @return list<string>
     */
    private function footerBandLines($page): array
    {
        try {
            $items = $page->getDataTm();
        } catch (\Throwable) {
            // Same trailing-image-only-page quirk pageText() guards against.
            return [];
        }

        $fragments = [];
        foreach ($items as $item) {
            $y = (float) ($item[0][5] ?? 0);
            if ($y >= self::FOOTER_MARGIN_POINTS) {
                continue;
            }
            $text = trim((string) $item[1]);
            // Skip very short fragments (stray punctuation/whitespace runs) —
            // stripping something that generic could eat real content.
            if (mb_strlen($text) >= 4) {
                $fragments[] = $text;
            }
        }

        return $fragments;
    }

    /** A trailing image-only page can trip a harmless array-offset warning in smalot's whitespace-position calc; that's promoted to an exception under Laravel's error handler, so each page is isolated. */
    private function pageText($page): string
    {
        try {
            return (string) $page->getText();
        } catch (\Throwable) {
            return '';
        }
    }

    private function rawContentStream($page): string
    {
        try {
            $contents = $page->getHeader()->get('Contents');
            if (is_object($contents) && method_exists($contents, 'getContent')) {
                return (string) $contents->getContent();
            }
            if (is_iterable($contents)) {
                $chunks = [];
                foreach ($contents as $el) {
                    if (is_object($el) && method_exists($el, 'getContent')) {
                        $chunks[] = (string) $el->getContent();
                    }
                }

                return implode("\n", $chunks);
            }
        } catch (\Throwable) {
            // No raw stream available for this page — highlighted-choice
            // detection simply won't find anything on it.
        }

        return '';
    }

    /**
     * Label-then-value extraction from the plain text layer, matching the
     * label-scanning approach WorkOrderController::parseExcel() uses for
     * the Excel work-order import.
     */
    /** @param list<string> $footerLines Geometrically-detected footer lines from footerBandLines(), skipped wherever they'd otherwise be mistaken for a field's value. */
    private function extractPlainFields(string $text, array $footerLines = []): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

        $result = [
            'inspector_name' => null,
            'completed_at' => null,
            'report_date' => null,
            'job_text' => null,
            'elevation_tag_guess' => null,
            'issue_description' => null,
        ];

        foreach ($lines as $line) {
            if (preg_match('/Completed by:\s*(.*?)\s*Job:/', $line, $m)) {
                $result['inspector_name'] = trim($m[1]) ?: null;
            }
            if (preg_match('/Completed at:\s*(.*?)\s*Location:/', $line, $m)) {
                $result['completed_at'] = $this->parseDateTime(trim($m[1]));
            }
        }

        // A label at the bottom of one page and its value at the top of the
        // next has the page's footer ("Downloaded on ..." / "Page N of M")
        // sitting between them in the concatenated text, since each page's
        // footer is the last thing in that page's text layer. Skip footer
        // lines rather than treating the first line after the label as the
        // value.
        $labelValue = function (string $label) use ($lines, $footerLines): ?string {
            $idx = array_search($label, $lines, true);
            if ($idx === false) {
                return null;
            }
            for ($i = $idx + 1; $i < count($lines); $i++) {
                if ($this->isFooterLine($lines[$i], $footerLines)) {
                    continue;
                }

                return $lines[$i] !== '' ? $lines[$i] : null;
            }

            return null;
        };

        $dateText = $labelValue(self::LABELS['date_issue_discovered'].'*');
        $result['report_date'] = $dateText ? $this->parseDate($dateText) : null;

        $result['job_text'] = $labelValue(self::LABELS['job'].'*');
        $result['elevation_tag_guess'] = $labelValue(self::LABELS['elevation'].'*');

        // The description can run past the next line and across a page
        // break; take everything after the label up to whichever comes
        // first: the end of the document, or the next field's label (this
        // form has since grown a "Picture of issue*" field after the
        // description — every real field label ends in "*", which a
        // free-text description never legitimately would). Footer lines are
        // skipped (not treated as a stopping point) so description text
        // continuing on the following page is kept.
        $descLabel = self::LABELS['description'].'*';
        $idx = array_search($descLabel, $lines, true);
        if ($idx !== false) {
            $descLines = [];
            for ($i = $idx + 1; $i < count($lines); $i++) {
                if ($this->isFooterLine($lines[$i], $footerLines)) {
                    continue;
                }
                if (str_ends_with($lines[$i], '*') && $lines[$i] !== $descLabel) {
                    break;
                }
                $descLines[] = $lines[$i];
            }
            $result['issue_description'] = trim(implode(' ', $descLines)) ?: null;
        }

        return $result;
    }

    /**
     * @param list<string> $footerLines Lines geometrically detected in the bottom margin band (see footerBandLines()) — checked first since it's exact and wording-independent. The static patterns remain as a fallback for whenever position data wasn't available (e.g. footerBandLines() hit the same trailing-page quirk pageText() guards against).
     */
    /**
     * True when $line is made up entirely of footer fragments (see
     * footerBandLines()) plus incidental whitespace — i.e. stripping every
     * known fragment out of it leaves nothing. Handles getText() gluing
     * several footer fragments onto one line with inconsistent separators,
     * which an exact-line or prefix match can't. Falls back to the old
     * wording-based patterns for whenever position data wasn't available.
     */
    private function isFooterLine(string $line, array $footerFragments = []): bool
    {
        if ($footerFragments !== []) {
            $remainder = str_replace($footerFragments, '', $line);
            if (trim($remainder) === '') {
                return true;
            }
        }

        return str_starts_with($line, 'Downloaded on') || (bool) preg_match('/^Page\s+\d+\s+of\s+\d+$/', $line);
    }

    /**
     * Walks the raw content stream tracking the active fill color, and for
     * each of the choice-style fields (a label followed by a bulleted list
     * of every option) returns whichever option's color differs from the
     * page's default body-text color — that's the one the form highlights
     * as selected. New Problem type categories added to the source form
     * need no code change here since the option list isn't hardcoded.
     */
    private function extractHighlightedChoices(string $rawStream): array
    {
        $runs = $this->colorTaggedTextRuns($rawStream);

        $result = [
            'replacement_needed' => null,
            'problem_type' => null,
        ];

        if (empty($runs)) {
            return $result;
        }

        $defaultColor = $this->modeColor($runs);

        $bounds = $this->labelBoundaries($runs, [
            self::LABELS['replacement_needed'],
            self::LABELS['problem_type'],
            self::LABELS['description'],
        ]);

        $replacementText = $this->selectedOptionInRange(
            $runs, $bounds[self::LABELS['replacement_needed']] ?? null, $bounds[self::LABELS['problem_type']] ?? null, $defaultColor
        );
        if ($replacementText !== null) {
            $result['replacement_needed'] = mb_strtolower($replacementText) === 'yes';
        }

        $result['problem_type'] = $this->selectedOptionInRange(
            $runs, $bounds[self::LABELS['problem_type']] ?? null, $bounds[self::LABELS['description']] ?? null, $defaultColor
        );

        return $result;
    }

    /**
     * @return array<int, array{text: string, color: string}>
     */
    private function colorTaggedTextRuns(string $rawStream): array
    {
        $color = null;
        $runs = [];

        foreach (preg_split('/\r\n|\r|\n/', $rawStream) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+scn$/', $line, $m)) {
                $color = sprintf('#%02x%02x%02x', (int) round($m[1] * 255), (int) round($m[2] * 255), (int) round($m[3] * 255));

                continue;
            }

            if (preg_match('/^<([0-9A-Fa-f]+)>\s*Tj$/', $line, $m)) {
                $text = $this->decodeHexRun($m[1]);
                if ($text !== '') {
                    $runs[] = ['text' => $text, 'color' => $color ?? '#000000'];
                }
            }
        }

        return $runs;
    }

    /** Decodes a Tj hex string, mapping the form's bullet glyph byte to "•" and dropping anything else non-ASCII (safe here — only used to read plain option labels, never free-text values with special characters). */
    private function decodeHexRun(string $hex): string
    {
        $bytes = hex2bin($hex) ?: '';
        $out = '';
        for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
            $byte = ord($bytes[$i]);
            if ($byte === 0xA5) {
                $out .= '•';
            } elseif ($byte >= 0x20 && $byte <= 0x7E) {
                $out .= chr($byte);
            }
        }

        return trim($out);
    }

    private function modeColor(array $runs): string
    {
        $counts = [];
        foreach ($runs as $run) {
            $counts[$run['color']] = ($counts[$run['color']] ?? 0) + 1;
        }
        arsort($counts);

        return array_key_first($counts) ?? '#000000';
    }

    /**
     * Finds the run index of each given label (matched with or without a
     * trailing "*" required-field marker).
     *
     * @return array<string, int>
     */
    private function labelBoundaries(array $runs, array $labels): array
    {
        $bounds = [];
        foreach ($runs as $i => $run) {
            foreach ($labels as $label) {
                if ($run['text'] === $label && ! isset($bounds[$label])) {
                    $bounds[$label] = $i;
                }
            }
        }

        return $bounds;
    }

    private function selectedOptionInRange(array $runs, ?int $start, ?int $end, string $defaultColor): ?string
    {
        if ($start === null) {
            return null;
        }
        $end ??= count($runs);

        for ($i = $start + 1; $i < $end; $i++) {
            $run = $runs[$i];
            if (! str_starts_with($run['text'], '•')) {
                continue;
            }
            if ($run['color'] !== $defaultColor) {
                return trim(mb_substr($run['text'], 1));
            }
        }

        return null;
    }

    private function parseDate(string $text): ?string
    {
        try {
            return Carbon::parse($text)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateTime(string $text): ?string
    {
        try {
            return Carbon::parse($text)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
