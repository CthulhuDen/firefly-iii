<?php

/**
 * NavigationMutationTest.php
 * Copyright (c) 2025 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Tests\unit\Support;

use Carbon\Carbon;
use FireflyIII\Support\Navigation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\integration\TestCase;

/**
 * @group unit-test
 * @group support
 * @group navigation
 *
 * @internal
 *
 * @coversNothing
 */
final class NavigationMutationTest extends TestCase
{
    private Navigation $navigation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->navigation = new Navigation();
    }

    #[DataProvider('provideFrequencies')]
    public function testEndOfPeriodDoesNotMutateInput(string $frequency): void
    {
        $originalDate = Carbon::parse('2023-01-01 12:00:00');
        $datePassed   = clone $originalDate;

        $this->navigation->endOfPeriod($datePassed, $frequency);

        $this->assertSame(
            $originalDate->toIso8601String(),
            $datePassed->toIso8601String(),
            sprintf('Input date was mutated when using frequency "%s"', $frequency)
        );
    }

    public static function provideFrequencies(): iterable
    {
        $frequencies = [
            '1D', 'daily',
            '1W', 'week', 'weekly',
            '1M', 'month', 'monthly',
            '3M', 'quarter', 'quarterly',
            '6M', 'half-year', 'half_year',
            '1Y', 'year', 'yearly',
            'custom',
            'last7', 'last30', 'last90', 'last365',
            'MTD', 'QTD', 'YTD',
        ];

        foreach ($frequencies as $frequency) {
            yield $frequency => [$frequency];
        }
    }
}
