<?php

namespace Tests\Unit\Enums;

use App\Enums\ComplexityLevel;
use PHPUnit\Framework\TestCase;

class ComplexityLevelTest extends TestCase
{
    public function test_trivial_has_lowest_weight(): void
    {
        $this->assertEquals(1, ComplexityLevel::Trivial->weight());
    }

    public function test_major_has_highest_weight(): void
    {
        $this->assertEquals(5, ComplexityLevel::Major->weight());
    }

    public function test_complexity_weights_are_ordered(): void
    {
        $this->assertLessThan(ComplexityLevel::Simple->weight(), ComplexityLevel::Trivial->weight());
        $this->assertLessThan(ComplexityLevel::Medium->weight(), ComplexityLevel::Simple->weight());
        $this->assertLessThan(ComplexityLevel::Complex->weight(), ComplexityLevel::Medium->weight());
        $this->assertLessThan(ComplexityLevel::Major->weight(), ComplexityLevel::Complex->weight());
    }

    public function test_is_higher_than(): void
    {
        $this->assertTrue(ComplexityLevel::Major->isHigherThan(ComplexityLevel::Complex));
        $this->assertTrue(ComplexityLevel::Complex->isHigherThan(ComplexityLevel::Medium));
        $this->assertTrue(ComplexityLevel::Medium->isHigherThan(ComplexityLevel::Simple));
        $this->assertTrue(ComplexityLevel::Simple->isHigherThan(ComplexityLevel::Trivial));

        $this->assertFalse(ComplexityLevel::Trivial->isHigherThan(ComplexityLevel::Simple));
        $this->assertFalse(ComplexityLevel::Simple->isHigherThan(ComplexityLevel::Medium));
    }

    public function test_from_score_returns_appropriate_complexity(): void
    {
        $this->assertEquals(ComplexityLevel::Trivial, ComplexityLevel::fromScore(0.1));
        $this->assertEquals(ComplexityLevel::Simple, ComplexityLevel::fromScore(0.3));
        $this->assertEquals(ComplexityLevel::Medium, ComplexityLevel::fromScore(0.5));
        $this->assertEquals(ComplexityLevel::Complex, ComplexityLevel::fromScore(0.7));
        $this->assertEquals(ComplexityLevel::Major, ComplexityLevel::fromScore(0.9));
    }

    public function test_estimated_hours_returns_positive_values(): void
    {
        foreach (ComplexityLevel::cases() as $level) {
            $hours = $level->estimatedHours();
            $this->assertIsArray($hours);
            $this->assertArrayHasKey('min', $hours);
            $this->assertArrayHasKey('max', $hours);
            $this->assertGreaterThan(0, $hours['min']);
            $this->assertGreaterThanOrEqual($hours['min'], $hours['max']);
        }
    }

    public function test_estimated_files_affected_returns_positive_values(): void
    {
        foreach (ComplexityLevel::cases() as $level) {
            $files = $level->estimatedFilesAffected();
            $this->assertIsArray($files);
            $this->assertArrayHasKey('min', $files);
            $this->assertArrayHasKey('max', $files);
            $this->assertGreaterThan(0, $files['min']);
            $this->assertGreaterThanOrEqual($files['min'], $files['max']);
        }
    }
}
