<?php

declare(strict_types=1);

namespace Modules\CurrencyConversion\Http\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use JetBrains\PhpStorm\NoReturn;
use Modules\CurrencyConversion\Http\Dtos\CurrencyConversionDto;
use Modules\CurrencyConversion\Http\Repositories\Interfaces\ICurrencyConversionRepository;
use Modules\CurrencyConversion\Http\Repositories\Interfaces\ISupportedCurrencyRepository;
use Modules\CurrencyConversion\Http\Services\Interfaces\ICurrencyConversionService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

readonly class CurrencyConversionService implements ICurrencyConversionService
{
    public function __construct(
        private ISupportedCurrencyRepository $supportedCurrencyRepository,
        private ICurrencyConversionRepository $currencyConversionRepository
    )
    {
    }

    /**
     * @param CurrencyConversionDto $dto
     * @return array
     */
    public function getCurrencyCalculation(CurrencyConversionDto $dto): array
    {
        $from = $dto->source_currency;
        $to = $dto->target_currency;
        $amount = $dto->value;

        $lastRates = $this->currencyConversionRepository->lastRates();

        $rates = json_decode($lastRates->json, true);

        // Convert the amount to EUR (base currency)
        $amountInEUR = $amount / $rates[$from];

        // Convert the amount from EUR to the target currency
        $amountCalculated =  $amountInEUR * $rates[$to];

        return [
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'amountCalculated' => $amountCalculated
        ];
    }

    #[NoReturn]
    public function syncSupportedFixerExchangeRate(): void
    {
        $apiKey = config('modules.envs.fixer-exchange-rate-currency-api-key');
        $apiDomain = config('modules.envs.fixer-exchange-rate-currency-api-domain');

        $apiEndpoint = sprintf('%s/api/symbols?access_key=%s', $apiDomain, $apiKey);

        $response = Http::get($apiEndpoint);

        if(!$response->successful()) {
            throw new BadRequestException($response->getBody()->getContents());
        }

        $json = $response->json();

        $success = $this->supportedCurrencyRepository->create($json['symbols']);

        if(!$success) {
            throw new BadRequestException(
                'Supported currency rates could not be created.'
            );
        }
    }

    /**
     * @return bool
     */
    public function syncFixerExchangeRateEndpoint(): bool
    {
        $apiKey = config('modules.envs.fixer-exchange-rate-currency-api-key');
        $apiDomain = config('modules.envs.fixer-exchange-rate-currency-api-domain');

        $apiEndpoint = sprintf('%s/api/latest?access_key=%s', $apiDomain, $apiKey);

        $response = Http::get($apiEndpoint);

        if(!$response->successful()) {
            throw new BadRequestException($response->getBody()->getContents());
        }

        $data = $response->json();

        return $this->currencyConversionRepository->createFixerExchangeRate($data);
    }

    /**
     * @return Collection
     */
    public function getSupportedExchangeRates(): Collection
    {
        return $this->supportedCurrencyRepository->all();
    }
}
