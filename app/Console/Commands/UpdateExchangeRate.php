<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

#[Signature('exchange:update-usd')]
#[Description('Consulta la API de rates.dolarvzla.com, actualiza la base de datos y la caché')]
class UpdateExchangeRate extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $response = Http::timeout(10)->get('https://rates.dolarvzla.com/bcv/current.json');

            if ($response->successful()) {
                /** @var array<string, mixed> $data */
                $data = $response->json();

                $currentData = $data['current'] ?? null;

                if ($currentData && isset($currentData['usd'])) {
                    $rate = $currentData['usd'];
                    $date = Carbon::parse($currentData['date'])->format('Y-m-d');

                    DB::transaction(function () use ($rate, $date) {
                        ExchangeRate::where('is_active', true)->update(['is_active' => false]);

                        $newRate = ExchangeRate::create([
                            'currency_from' => 'USD',
                            'currency_to' => 'BS',
                            'rate' => $rate,
                            'date' => $date,
                            'is_active' => true,
                        ]);

                        Cache::forever('exchange_rate', $newRate->rate);
                    });

                    $this->info("¡Éxito! Tasa actualizada y en caché: {$rate} Bs/USD");

                    return Command::SUCCESS;
                }
            }

            $this->error('La API respondió, pero no se encontró la tasa USD.');

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Error de conexión o guardado: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
