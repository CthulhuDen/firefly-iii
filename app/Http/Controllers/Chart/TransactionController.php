<?php

/**
 * TransactionController.php
 * Copyright (c) 2020 james@firefly-iii.org
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

use Carbon\Carbon;
use FireflyIII\Enums\TransactionTypeEnum;
use FireflyIII\Generator\Chart\Basic\GeneratorInterface;
use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Http\Controllers\Controller;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use FireflyIII\Support\CacheProperties;
use FireflyIII\Support\Facades\Steam;
use FireflyIII\Support\Http\Api\ExchangeRateConverter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class TransactionController
 */
class TransactionController extends Controller
{
    private GeneratorInterface $generator;
    private CurrencyRepositoryInterface $currencyRepository;

    /**
     * TransactionController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(
            function (Request $request, $next) {
                $this->generator = app(GeneratorInterface::class);
                $this->currencyRepository = app(CurrencyRepositoryInterface::class);

                return $next($request);
            }
        );
    }

    /**
     * @return JsonResponse
     */
    public function budgets(Carbon $start, Carbon $end)
    {
        $cache     = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty('chart.transactions.budgets');
        if ($cache->has()) {
            return response()->json($cache->get());
        }

        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector->setRange($start, $end);
        $collector->withBudgetInformation();
        $collector->setTypes([TransactionTypeEnum::WITHDRAWAL->value]);

        $result = $collector->getExtractedJournals();

        $getBudget = fn (array $journal) => $journal['budget_name'] ?? (string) trans('firefly.no_budget');
        $chart     = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($result, $getBudget)
            : $this->formatPieChartMultiCurrency($result, $getBudget);

        $cache->store($chart);

        return response()->json($chart);
    }

    /**
     * @return JsonResponse
     */
    public function categories(string $objectType, Carbon $start, Carbon $end)
    {
        $cache     = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty($objectType);
        $cache->addProperty('chart.transactions.categories');
        if ($cache->has()) {
            return response()->json($cache->get());
        }

        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector->setRange($start, $end);
        $collector->withCategoryInformation();

        if ('withdrawal' === $objectType) {
            $collector->setTypes([TransactionTypeEnum::WITHDRAWAL->value]);
        }
        if ('deposit' === $objectType) {
            $collector->setTypes([TransactionTypeEnum::DEPOSIT->value]);
        }
        if ('transfer' === $objectType || 'transfers' === $objectType) {
            $collector->setTypes([TransactionTypeEnum::TRANSFER->value]);
        }

        $result = $collector->getExtractedJournals();

        $getCategory = fn (array $journal) => $journal['category_name'] ?? (string) trans('firefly.no_category');
        $chart       = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($result, $getCategory)
            : $this->formatPieChartMultiCurrency($result, $getCategory);

        $cache->store($chart);

        return response()->json($chart);
    }

    /**
     * @return JsonResponse
     */
    public function destinationAccounts(string $objectType, Carbon $start, Carbon $end)
    {
        $cache     = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty($objectType);
        $cache->addProperty('chart.transactions.destinations');
        if ($cache->has()) {
            return response()->json($cache->get());
        }

        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector->setRange($start, $end);
        $collector->withAccountInformation();

        if ('withdrawal' === $objectType) {
            $collector->setTypes([TransactionTypeEnum::WITHDRAWAL->value]);
        }
        if ('deposit' === $objectType) {
            $collector->setTypes([TransactionTypeEnum::DEPOSIT->value]);
        }
        if ('transfer' === $objectType || 'transfers' === $objectType) {
            $collector->setTypes([TransactionTypeEnum::TRANSFER->value]);
        }

        $result = $collector->getExtractedJournals();

        $getDestination = fn (array $journal) => $journal['destination_account_name'];
        $chart          = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($result, $getDestination)
            : $this->formatPieChartMultiCurrency($result, $getDestination);

        $cache->store($chart);

        return response()->json($chart);
    }

    /**
     * @return JsonResponse
     */
    public function sourceAccounts(string $objectType, Carbon $start, Carbon $end)
    {
        $cache     = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty($objectType);
        $cache->addProperty('chart.transactions.sources');
        if ($cache->has()) {
            return response()->json($cache->get());
        }

        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector->setRange($start, $end);
        $collector->withAccountInformation();

        if ('withdrawal' === $objectType) {
            $collector->setTypes([TransactionTypeEnum::WITHDRAWAL->value]);
        }
        if ('deposit' === $objectType) {
            $collector->setTypes([TransactionTypeEnum::DEPOSIT->value]);
        }
        if ('transfer' === $objectType || 'transfers' === $objectType) {
            $collector->setTypes([TransactionTypeEnum::TRANSFER->value]);
        }

        $result = $collector->getExtractedJournals();

        $getSource = fn (array $journal) => $journal['source_account_name'];
        $chart     = $this->convertToPrimary
            ? $this->formatPieChartPrimaryCurrency($result, $getSource)
            : $this->formatPieChartMultiCurrency($result, $getSource);

        $cache->store($chart);

        return response()->json($chart);
    }

    private function formatPieChartMultiCurrency(array $transactions, callable $getTitle): array
    {
        $result = [];

        // loop expenses.
        foreach ($transactions as $journal) {
            $title = sprintf('%s (%s)', $getTitle($journal), $journal['currency_symbol']);
            $result[$title] ??= [
                'amount'          => '0',
                'currency_symbol' => $journal['currency_symbol'],
                'currency_code'   => $journal['currency_code'],
            ];

            $amount                   = Steam::positive($journal['amount']);
            $result[$title]['amount'] = bcadd($result[$title]['amount'], $amount);
        }

        return $this->generator->multiCurrencyPieChart($result);
    }

    private function formatPieChartPrimaryCurrency(array $transactions, callable $getTitle): array
    {
        $converter  = new ExchangeRateConverter();
        $currencies = $this->currencyRepository->get()->keyBy('id');

        $amountByTitleByCurrency = [];
        foreach ($transactions as $journal) {
            $title  = $getTitle($journal);
            $amount = Steam::positive($journal['amount']);
            $amountByTitleByCurrency[$title][$journal['currency_id']]
                = bcadd($amountByTitleByCurrency[$title][$journal['currency_id']] ?? '0', $amount);
        }

        $result = [];
        foreach ($amountByTitleByCurrency as $title => $amountByCurrency) {
            $result[$title] ??= [
                'amount'          => '0',
                'currency_symbol' => $this->primaryCurrency->symbol,
                'currency_code'   => $this->primaryCurrency->code,
            ];

            foreach ($amountByCurrency as $currencyId => $amount) {
                $originalCurrency = $currencies[$currencyId];

                $amountPC = $converter->convert($originalCurrency, $this->primaryCurrency, Carbon::now(), $amount);
                $result[$title]['amount'] = bcadd($result[$title]['amount'], $amountPC);
            }
        }

        return $this->generator->multiCurrencyPieChart($result);
    }
}
