<?php

namespace Tests\Feature;

use App\Services\QualityReportPdfExtractor;
use Tests\TestCase;

/**
 * Regression coverage for the footer-vs-page-break bug: this sample's
 * "Short Description of Issue" answer is immediately followed on the same
 * page by the form's persistent footer ("Page 1 of 2 ... Powered by ...
 * Downloaded on ..."), which getText() glues onto one line with inconsistent
 * separators, and then a later "Picture of issue*" field. Without the
 * bottom-margin exclusion and the next-label stop condition, both leak into
 * issue_description.
 */
class QualityReportPdfExtractorTest extends TestCase
{
    public function test_footer_and_trailing_field_are_excluded_from_description(): void
    {
        $extractor = new QualityReportPdfExtractor;
        $result = $extractor->extract(__DIR__.'/../Fixtures/quality-report-sample.pdf');

        $this->assertSame("Labeled cut size 119½ actually 119⅞", $result['issue_description']);
        $this->assertStringNotContainsString('Downloaded on', $result['issue_description']);
        $this->assertStringNotContainsString('Page 1 of 2', $result['issue_description']);
        $this->assertStringNotContainsString('Picture of issue', $result['issue_description']);
    }

    public function test_other_plain_fields_still_extract_correctly(): void
    {
        $extractor = new QualityReportPdfExtractor;
        $result = $extractor->extract(__DIR__.'/../Fixtures/quality-report-sample.pdf');

        $this->assertSame('Timothy Chaffee', $result['inspector_name']);
        $this->assertSame('2026-09-02', $result['report_date']);
        $this->assertSame('Howell south west', $result['job_text']);
        $this->assertSame('24A e12', $result['elevation_tag_guess']);
        $this->assertFalse($result['replacement_needed']);
        $this->assertSame('Length/Size Issue', $result['problem_type']);
    }
}
