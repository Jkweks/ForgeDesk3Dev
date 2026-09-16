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

    public function extract(string $filePath): array
    {
        $document = (new Parser)->parseFile($filePath);
        $pages = $document->getPages();

        $fullText = implode("\n", array_map(fn ($p) => $this->pageText($p), $pages));
        $rawStream = implode("\n", array_map(fn ($p) => $this->rawContentStream($p), $pages));

        $plain = $this->extractPlainFields($fullText);
        $choices = $this->extractHighlightedChoices($rawStream);

        return array_merge($plain, $choices, [
            'raw_extracted_text' => $fullText,
        ]);
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
    private function extractPlainFields(string $text): array
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
        $labelValue = function (string $label) use ($lines): ?string {
            $idx = array_search($label, $lines, true);
            if ($idx === false) {
                return null;
            }
            for ($i = $idx + 1; $i < count($lines); $i++) {
                if ($this->isFooterLine($lines[$i])) {
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
        // break; take everything after the label to the end of the
        // document, skipping (not stopping at) each page's footer so
        // description text continuing on the following page is kept.
        $descLabel = self::LABELS['description'].'*';
        $idx = array_search($descLabel, $lines, true);
        if ($idx !== false) {
            $descLines = [];
            for ($i = $idx + 1; $i < count($lines); $i++) {
                if ($this->isFooterLine($lines[$i])) {
                    continue;
                }
                $descLines[] = $lines[$i];
            }
            $result['issue_description'] = trim(implode(' ', $descLines)) ?: null;
        }

        return $result;
    }

    private function isFooterLine(string $line): bool
    {
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
