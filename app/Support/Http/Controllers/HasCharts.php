<?php

namespace FireflyIII\Support\Http\Controllers;

use Carbon\Carbon;
use FireflyIII\Generator\Chart\Basic\GeneratorInterface;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use FireflyIII\Support\Facades\Steam;
use FireflyIII\Support\Http\Api\ExchangeRateConverter;

trait HasCharts
{
    private GeneratorInterface $generator;
    private CurrencyRepositoryInterface $currencyRepository;

    private function formatPieChartMultiCurrency(array $byCurrency, ?string $units, callable $getTitle): array
    {
        $result = [];

        // loop expenses.
        foreach ($byCurrency as $currency) {
            if ($units === null) {
                foreach ($currency['transaction_journals'] as $journal) {
                    $title = sprintf('%s (%s)', $getTitle($journal, $currency), $currency['currency_name']);
                    $result[$title] ??= [
                        'amount'          => '0',
                        'currency_symbol' => $currency['currency_symbol'],
                        'currency_code'   => $currency['currency_code'],
                    ];

                    $amount                   = Steam::positive($journal['amount']);
                    $result[$title]['amount'] = bcadd($result[$title]['amount'], $amount);
                }
            } else {
                /** @var array $unit */
                foreach ($currency[$units] as $unit) {
                    foreach ($unit['transaction_journals'] as $journal) {
                        $title = sprintf('%s (%s)', $getTitle($journal, $unit, $currency), $currency['currency_name']);
                        $result[$title] ??= [
                            'amount'          => '0',
                            'currency_symbol' => $currency['currency_symbol'],
                            'currency_code'   => $currency['currency_code'],
                        ];

                        $amount                   = Steam::positive($journal['amount']);
                        $result[$title]['amount'] = bcadd($result[$title]['amount'], $amount);
                    }
                }
            }
        }

        $this->generator ??= app(GeneratorInterface::class);
        return $this->generator->multiCurrencyPieChart($result);
    }

    private function formatPieChartPrimaryCurrency(array $byCurrency, ?string $units, callable $getTitle): array
    {
        $converter  = new ExchangeRateConverter();
        $this->currencyRepository ??= app(CurrencyRepositoryInterface::class);
        $currencies = $this->currencyRepository->get()->keyBy('id');

        $result = [];
        foreach ($byCurrency as $currency) {
            $originalCurrency = $currencies[$currency['currency_id']];
            $amountByTitle = [];

            if ($units === null) {
                foreach ($currency['transaction_journals'] as $journal) {
                    $title = $getTitle($journal, $currency);

                    $amount                = Steam::positive($journal['amount']);
                    $amountByTitle[$title] = bcadd($amountByTitle[$title] ?? '0', $amount);
                }
            } else {
                /** @var array $unit */
                foreach ($currency[$units] as $unit) {
                    foreach ($unit['transaction_journals'] as $journal) {
                        $title = $getTitle($journal, $unit, $currency);

                        $amount                = Steam::positive($journal['amount']);
                        $amountByTitle[$title] = bcadd($amountByTitle[$title] ?? '0', $amount);
                    }
                }
            }

            foreach ($amountByTitle as $title => $amount) {
                $result[$title] ??= [
                    'amount'          => '0',
                    'currency_symbol' => $this->primaryCurrency->symbol,
                    'currency_code'   => $this->primaryCurrency->code,
                ];

                $amountPC = $converter->convert($originalCurrency, $this->primaryCurrency, Carbon::now(), $amount);
                $result[$title]['amount'] = bcadd($result[$title]['amount'], $amountPC);
            }
        }

        $this->generator ??= app(GeneratorInterface::class);
        return $this->generator->multiCurrencyPieChart($result);
    }
}
