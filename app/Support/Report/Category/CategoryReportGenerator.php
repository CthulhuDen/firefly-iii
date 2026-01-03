<?php

/*
 * CategoryReportGenerator.php
 * Copyright (c) 2021 james@firefly-iii.org
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

namespace FireflyIII\Support\Report\Category;

use Carbon\Carbon;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Repositories\Category\NoCategoryRepositoryInterface;
use FireflyIII\Repositories\Category\OperationsRepositoryInterface;
use FireflyIII\Support\Facades\Steam;
use FireflyIII\User;
use Illuminate\Support\Collection;

/**
 * Class CategoryReportGenerator
 */
class CategoryReportGenerator
{
    private Collection                             $accounts;
    private Carbon                                 $end;
    private readonly NoCategoryRepositoryInterface $noCatRepository;
    private readonly OperationsRepositoryInterface $opsRepository;
    private array                                  $report;
    private Carbon                                 $start;

    /**
     * CategoryReportGenerator constructor.
     */
    public function __construct()
    {
        $this->opsRepository   = app(OperationsRepositoryInterface::class);
        $this->noCatRepository = app(NoCategoryRepositoryInterface::class);
    }

    public function getReport(): array
    {
        return $this->report;
    }

    /**
     * Generate the array required to show the overview of categories on the
     * default report.
     */
    public function operations(): void
    {
        $earnedWith     = $this->opsRepository->listIncome($this->start, $this->end, $this->accounts);
        $spentWith      = $this->opsRepository->listExpenses($this->start, $this->end, $this->accounts);

        // also transferred out and transferred into these accounts in this category:
        $transferredIn  = $this->opsRepository->listTransferredIn($this->start, $this->end, $this->accounts);
        $transferredOut = $this->opsRepository->listTransferredOut($this->start, $this->end, $this->accounts);

        $earnedWithout  = $this->noCatRepository->listIncome($this->start, $this->end, $this->accounts);
        $spentWithout   = $this->noCatRepository->listExpenses($this->start, $this->end, $this->accounts);

        $primaryCurrency  = app('amount')->getPrimaryCurrencyByUserGroup(auth()->user()->userGroup);
        $convertToPrimary = app('amount')->convertToPrimary(auth()->user());

        $this->report   = [
            'categories' => [],
            'sums'       => [],
        ];

        if ($convertToPrimary) {
            $this->report['sums'][$primaryCurrency->id] ??= [
                'spent'                   => '0',
                'earned'                  => '0',
                'sum'                     => '0',
                'currency_id'             => $primaryCurrency->id,
                'currency_symbol'         => $primaryCurrency->symbol,
                'currency_name'           => $primaryCurrency->name,
                'currency_code'           => $primaryCurrency->code,
                'currency_decimal_places' => $primaryCurrency->decimal_places,
            ];
        }

        // needs four for-each loops.
        foreach ([$earnedWith, $spentWith, $earnedWithout, $spentWithout, $transferredIn, $transferredOut] as $data) {
            $this->processOpsArray($data, $primaryCurrency, $convertToPrimary);
        }
    }

    public function sort()
    {
        uasort($this->report['categories'], function (array $cat1, array $cat2): int {
            [$val1, $val2] = [$cat1['sum'], $cat2['sum']];
            [$pos1, $pos2] = [bccomp($val1, '0') > 0, bccomp($val2, '0') > 0];

            return ($pos2 <=> $pos1)
                ?: bccomp(Steam::positive($val2), Steam::positive($val1))
                ;
        });
    }

    public function setAccounts(Collection $accounts): void
    {
        $this->accounts = $accounts;
    }

    public function setEnd(Carbon $end): void
    {
        $this->end = $end;
    }

    public function setStart(Carbon $start): void
    {
        $this->start = $start;
    }

    public function setUser(User $user): void
    {
        $this->noCatRepository->setUser($user);
        $this->opsRepository->setUser($user);
    }

    private function processCategoryRow(int $currencyId, array $currencyRow, int $categoryId, array $categoryRow, TransactionCurrency $primaryCurrency, bool $convertToPrimary): void
    {
        $key       = sprintf('%s-%s', $currencyId, $categoryId);
        $getAmount = fn (array $journal) => $journal['amount'];

        if ($convertToPrimary) {
            $key = $categoryId;

            $getAmount = match (true) {
                $currencyId === $primaryCurrency->id => fn (array $journal) => $journal['amount'],
                default => fn (array $journal) => $journal['pc_amount'],
            };

            $currencyId = $primaryCurrency->id;
            $currencyRow = [
                'currency_id'             => $primaryCurrency->id,
                'currency_symbol'         => $primaryCurrency->symbol,
                'currency_name'           => $primaryCurrency->name,
                'currency_code'           => $primaryCurrency->code,
                'currency_decimal_places' => $primaryCurrency->decimal_places,
            ];
        }

        $this->report['categories'][$key] ??= [
            'id'                      => $categoryId,
            'title'                   => $categoryRow['name'],
            'currency_id'             => $currencyRow['currency_id'],
            'currency_symbol'         => $currencyRow['currency_symbol'],
            'currency_name'           => $currencyRow['currency_name'],
            'currency_code'           => $currencyRow['currency_code'],
            'currency_decimal_places' => $currencyRow['currency_decimal_places'],
            'spent'                   => '0',
            'earned'                  => '0',
            'sum'                     => '0',
        ];
        // loop journals:
        foreach ($categoryRow['transaction_journals'] as $journal) {
            // sum of sums
            $this->report['sums'][$currencyId]['sum']    = bcadd((string)$this->report['sums'][$currencyId]['sum'], $getAmount($journal));
            // sum of spent:
            $this->report['sums'][$currencyId]['spent']  = -1 === bccomp((string)$journal['amount'], '0') ? bcadd(
                (string)$this->report['sums'][$currencyId]['spent'],
                $getAmount($journal),
            ) : $this->report['sums'][$currencyId]['spent'];
            // sum of earned
            $this->report['sums'][$currencyId]['earned'] = 1 === bccomp((string)$journal['amount'], '0') ? bcadd(
                (string)$this->report['sums'][$currencyId]['earned'],
                $getAmount($journal),
            ) : $this->report['sums'][$currencyId]['earned'];

            // sum of category
            $this->report['categories'][$key]['sum']     = bcadd((string)$this->report['categories'][$key]['sum'], $getAmount($journal));
            // total spent in category
            $this->report['categories'][$key]['spent']   = -1 === bccomp((string)$journal['amount'], '0') ? bcadd(
                (string)$this->report['categories'][$key]['spent'],
                $getAmount($journal),
            ) : $this->report['categories'][$key]['spent'];
            // total earned in category
            $this->report['categories'][$key]['earned']  = 1 === bccomp((string)$journal['amount'], '0') ? bcadd(
                (string)$this->report['categories'][$key]['earned'],
                $getAmount($journal),
            ) : $this->report['categories'][$key]['earned'];
        }
    }

    private function processCurrencyArray(int $currencyId, array $currencyRow, TransactionCurrency $primaryCurrency, bool $convertToPrimary): void
    {
        if (!$convertToPrimary) {
            $this->report['sums'][$currencyId] ??= [
                'spent'                   => '0',
                'earned'                  => '0',
                'sum'                     => '0',
                'currency_id'             => $currencyRow['currency_id'],
                'currency_symbol'         => $currencyRow['currency_symbol'],
                'currency_name'           => $currencyRow['currency_name'],
                'currency_code'           => $currencyRow['currency_code'],
                'currency_decimal_places' => $currencyRow['currency_decimal_places'],
            ];
        }

        /**
         * @var int   $categoryId
         * @var array $categoryRow
         */
        foreach ($currencyRow['categories'] as $categoryId => $categoryRow) {
            $this->processCategoryRow($currencyId, $currencyRow, $categoryId, $categoryRow, $primaryCurrency, $convertToPrimary);
        }
    }

    /**
     * Process one of the spent arrays from the operations method.
     */
    private function processOpsArray(array $data, TransactionCurrency $primaryCurrency, bool $convertToPrimary): void
    {
        /**
         * @var int   $currencyId
         * @var array $currencyRow
         */
        foreach ($data as $currencyId => $currencyRow) {
            $this->processCurrencyArray($currencyId, $currencyRow, $primaryCurrency, $convertToPrimary);
        }

        ksort($this->report['sums']);
        if (isset($this->report['sums'][$primaryCurrency->id])) {
            $primarySum = $this->report['sums'][$primaryCurrency->id];
            unset($this->report['sums'][$primaryCurrency->id]);

            $this->report['sums'] = [$primaryCurrency->id => $primarySum] + $this->report['sums'];
        }
    }
}
