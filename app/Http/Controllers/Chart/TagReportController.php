<?php

/**
 * TagReportController.php
 * Copyright (c) 2019 james@firefly-iii.org
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

namespace FireflyIII\Http\Controllers\Chart;

use FireflyIII\Support\Facades\Navigation;
use Carbon\Carbon;
use FireflyIII\Generator\Chart\Basic\GeneratorInterface;
use FireflyIII\Http\Controllers\Controller;
use FireflyIII\Models\Tag;
use FireflyIII\Repositories\Tag\OperationsRepositoryInterface;
use FireflyIII\Support\Facades\Steam;
use FireflyIII\Support\Http\Controllers\AugumentData;
use FireflyIII\Support\Http\Controllers\HasCharts;
use FireflyIII\Support\Http\Controllers\TransactionCalculation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Class TagReportController
 */
class TagReportController extends Controller
{
    use AugumentData;
    use TransactionCalculation;
    use HasCharts;

    private GeneratorInterface $generator;
    private OperationsRepositoryInterface $opsRepository;

    /**
     * TagReportController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(
            function ($request, $next) {
                $this->generator = app(GeneratorInterface::class);
                $this->opsRepository = app(OperationsRepositoryInterface::class);

                return $next($request);
            }
        );
    }

    public function budgetExpense(Collection $accounts, Collection $tags, Carbon $start, Carbon $end): JsonResponse
    {
        $spent     = $this->opsRepository->listExpenses($start, $end, $accounts, $tags);
        $getBudget = fn (array $journal) => $journal['budget_name'] ?? trans('firefly.no_budget');
        $data      = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($spent, 'tags', $getBudget)
            : $this->formatPieChartMultiCurrency($spent, 'tags', $getBudget);
        return response()->json($data);
    }

    public function categoryExpense(Collection $accounts, Collection $tags, Carbon $start, Carbon $end): JsonResponse
    {
        $spent       = $this->opsRepository->listExpenses($start, $end, $accounts, $tags);
        $getCategory = fn (array $journal) => $journal['category_name'] ?? trans('firefly.no_category');
        $data        = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($spent, 'tags', $getCategory)
            : $this->formatPieChartMultiCurrency($spent, 'tags', $getCategory);
        return response()->json($data);
    }

    public function categoryIncome(Collection $accounts, Collection $tags, Carbon $start, Carbon $end): JsonResponse
    {
        $earned      = $this->opsRepository->listIncome($start, $end, $accounts, $tags);
        $getCategory = fn (array $journal) => $journal['category_name'] ?? trans('firefly.no_category');
        $data        = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($earned, 'tags', $getCategory)
            : $this->formatPieChartMultiCurrency($earned, 'tags', $getCategory);
        return response()->json($data);
    }

    public function destinationExpense(Collection $accounts, Collection $tags, Carbon $start, Carbon $end): JsonResponse
    {
        $spent          = $this->opsRepository->listExpenses($start, $end, $accounts, $tags);
        $getDestination = fn (array $journal) => $journal['destination_account_name'] ?? trans('firefly.empty');
        $data           = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($spent, 'tags', $getDestination)
            : $this->formatPieChartMultiCurrency($spent, 'tags', $getDestination);
        return response()->json($data);
    }

    public function destinationIncome(Collection $accounts, Collection $tags, Carbon $start, Carbon $end): JsonResponse
    {
        $earned         = $this->opsRepository->listIncome($start, $end, $accounts, $tags);
        $getDestination = fn (array $journal) => $journal['destination_account_name'] ?? trans('firefly.empty');
        $data           = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($earned, 'tags', $getDestination)
            : $this->formatPieChartMultiCurrency($earned, 'tags', $getDestination);
        return response()->json($data);
    }

    /**
     * Generate main tag overview chart.
     */
    public function mainChart(Collection $accounts, Tag $tag, Carbon $start, Carbon $end): JsonResponse
    {
        $chartData = [];
        $spent     = $this->opsRepository->listExpenses($start, $end, $accounts, new Collection()->push($tag));
        $earned    = $this->opsRepository->listIncome($start, $end, $accounts, new Collection()->push($tag));
        $format    = Navigation::preferredCarbonLocalizedFormat($start, $end);

        // loop expenses.
        foreach ($spent as $currency) {
            // add things to chart Data for each currency:
            $spentKey = sprintf('%d-spent', $currency['currency_id']);
            $chartData[$spentKey] ??= [
                'label'           => sprintf(
                    '%s (%s)',
                    (string) trans('firefly.spent_in_specific_tag', ['tag' => $tag->tag]),
                    $currency['currency_name']
                ),
                'type'            => 'bar',
                'currency_symbol' => $currency['currency_symbol'],
                'currency_code'   => $currency['currency_code'],
                'currency_id'     => $currency['currency_id'],
                'entries'         => $this->makeEntries($start, $end),
            ];

            foreach ($currency['tags'] as $currentTag) {
                foreach ($currentTag['transaction_journals'] as $journal) {
                    $key                                   = $journal['date']->isoFormat($format);
                    $amount                                = Steam::positive($journal['amount']);
                    $chartData[$spentKey]['entries'][$key] ??= '0';
                    $chartData[$spentKey]['entries'][$key] = bcadd($chartData[$spentKey]['entries'][$key], $amount);
                }
            }
        }

        // loop income.
        foreach ($earned as $currency) {
            // add things to chart Data for each currency:
            $spentKey = sprintf('%d-earned', $currency['currency_id']);
            $chartData[$spentKey] ??= [
                'label'           => sprintf(
                    '%s (%s)',
                    (string) trans('firefly.earned_in_specific_tag', ['tag' => $tag->tag]),
                    $currency['currency_name']
                ),
                'type'            => 'bar',
                'currency_symbol' => $currency['currency_symbol'],
                'currency_code'   => $currency['currency_code'],
                'currency_id'     => $currency['currency_id'],
                'entries'         => $this->makeEntries($start, $end),
            ];

            foreach ($currency['tags'] as $currentTag) {
                foreach ($currentTag['transaction_journals'] as $journal) {
                    $key                                   = $journal['date']->isoFormat($format);
                    $amount                                = Steam::positive($journal['amount']);
                    $chartData[$spentKey]['entries'][$key] ??= '0';
                    $chartData[$spentKey]['entries'][$key] = bcadd($chartData[$spentKey]['entries'][$key], $amount);
                }
            }
        }

        $data      = $this->generator->multiSet($chartData);

        return response()->json($data);
    }

    /**
     * TODO duplicate function
     */
    private function makeEntries(Carbon $start, Carbon $end): array
    {
        $return         = [];
        $format         = Navigation::preferredCarbonLocalizedFormat($start, $end);
        $preferredRange = Navigation::preferredRangeFormat($start, $end);
        $currentStart   = clone $start;
        while ($currentStart <= $end) {
            $currentEnd   = Navigation::endOfPeriod($currentStart, $preferredRange);
            $key          = $currentStart->isoFormat($format);
            $return[$key] = '0';
            $currentStart = clone $currentEnd;
            $currentStart->addDay()->startOfDay();
        }

        return $return;
    }

    public function sourceExpense(Collection $accounts, Collection $tags, Carbon $start, Carbon $end): JsonResponse
    {
        $spent     = $this->opsRepository->listExpenses($start, $end, $accounts, $tags);
        $getSource = fn (array $journal) => $journal['source_account_name'] ?? trans('firefly.empty');
        $data      = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($spent, 'tags', $getSource)
            : $this->formatPieChartMultiCurrency($spent, 'tags', $getSource);
        return response()->json($data);
    }

    public function sourceIncome(Collection $accounts, Collection $tags, Carbon $start, Carbon $end): JsonResponse
    {
        $earned    = $this->opsRepository->listIncome($start, $end, $accounts, $tags);
        $getSource = fn (array $journal) => $journal['source_account_name'] ?? trans('firefly.empty');
        $data      = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($earned, 'tags', $getSource)
            : $this->formatPieChartMultiCurrency($earned, 'tags', $getSource);
        return response()->json($data);
    }

    public function tagExpense(Collection $accounts, Collection $tags, Carbon $start, Carbon $end): JsonResponse
    {
        $spent  = $this->opsRepository->listExpenses($start, $end, $accounts, $tags);
        $getTag = fn (array $journal, array $tag) => $tag['name'];
        $data   = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($spent, 'tags', $getTag)
            : $this->formatPieChartMultiCurrency($spent, 'tags', $getTag);
        return response()->json($data);
    }

    public function tagIncome(Collection $accounts, Collection $tags, Carbon $start, Carbon $end): JsonResponse
    {
        $earned = $this->opsRepository->listIncome($start, $end, $accounts, $tags);
        $getTag = fn (array $journal, array $tag) => $tag['name'];
        $data   = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($earned, 'tags', $getTag)
            : $this->formatPieChartMultiCurrency($earned, 'tags', $getTag);
        return response()->json($data);
    }
}
