<?php

namespace App\Http\Controllers;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Http\Controllers\Controller;
use App\Models\Story;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request, $year = null)
    {
        // Recupera l'anno e i quarter disponibili tramite una funzione separata
        [$year, $availableQuarters, $error] = $this->getYearAndQuarters($year);

        // Se c'è un errore (ad esempio, l'anno è nel futuro), lo restituiamo subito
        if ($error) {
            return view('reports.index')->with('error', $error);
        }

        // Ottieni i report per Tipo e Stato tramite funzioni separate
        $reportByType = $this->generateReportByType($year, $availableQuarters);
        [$reportByStatus, $totals] = $this->generateReportByStatus($year, $availableQuarters); // Ora include i totali
        // Ottieni i report per Utente e somma totale
        [$reportByUser, $totalOverall] = $this->generateReportByUser($year, $availableQuarters);

        return view('reports.index', compact('reportByType', 'reportByStatus', 'totals', 'year', 'availableQuarters', 'reportByUser', 'totalOverall',));
    }


    /**
     * Genera il report per Tipo di Storia
     */
    private function generateReportByType($year, $availableQuarters)
    {
        $totalStories = $year === 'All Time' ? Story::count() : Story::whereYear('updated_at', $year)->count();

        $reportByType = [];
        foreach (StoryType::cases() as $type) {
            $yearTotal = $year === 'All Time' ? Story::where('type', $type->value)->count() : Story::where('type', $type->value)->whereYear('updated_at', $year)->count();
            $q1 = $year === 'All Time' ? Story::where('type', $type->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 1')->count() : Story::where('type', $type->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 1')->count();
            $q2 = $year === 'All Time' ? Story::where('type', $type->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 2')->count() : Story::where('type', $type->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 2')->count();
            $q3 = $year === 'All Time' ? Story::where('type', $type->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 3')->count() : Story::where('type', $type->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 3')->count();
            $q4 = $year === 'All Time' ? Story::where('type', $type->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 4')->count() : Story::where('type', $type->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 4')->count();

            // Calcola la percentuale rispetto al totale
            $yearPercentage = $totalStories > 0 ? ($yearTotal / $totalStories) * 100 : 0;
            $q1Percentage = $totalStories > 0 ? ($q1 / $totalStories) * 100 : 0;
            $q2Percentage = $totalStories > 0 ? ($q2 / $totalStories) * 100 : 0;
            $q3Percentage = $totalStories > 0 ? ($q3 / $totalStories) * 100 : 0;
            $q4Percentage = $totalStories > 0 ? ($q4 / $totalStories) * 100 : 0;

            $reportByType[] = [
                'type' => $type->value,
                'year_total' => $yearTotal,
                'year_percentage' => $yearPercentage,
                'q1' => $q1,
                'q1_percentage' => $q1Percentage,
                'q2' => $q2,
                'q2_percentage' => $q2Percentage,
                'q3' => $q3,
                'q3_percentage' => $q3Percentage,
                'q4' => $q4,
                'q4_percentage' => $q4Percentage,
            ];
        }

        return $reportByType;
    }

    /**
     * Genera il report per Stato di Storia
     */
    private function generateReportByStatus($year, $availableQuarters)
    {
        $totalStories = $year === 'All Time' ? Story::count() : Story::whereYear('updated_at', $year)->count();

        $reportByStatus = [];
        $totals = [
            'year_total' => 0,
            'q1' => 0,
            'q2' => 0,
            'q3' => 0,
            'q4' => 0,
        ];

        foreach (StoryStatus::cases() as $status) {
            $yearTotal = $year === 'All Time' ? Story::where('status', $status->value)->count() : Story::where('status', $status->value)->whereYear('updated_at', $year)->count();
            $q1 = $year === 'All Time' ? Story::where('status', $status->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 1')->count() : Story::where('status', $status->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 1')->count();
            $q2 = $year === 'All Time' ? Story::where('status', $status->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 2')->count() : Story::where('status', $status->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 2')->count();
            $q3 = $year === 'All Time' ? Story::where('status', $status->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 3')->count() : Story::where('status', $status->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 3')->count();
            $q4 = $year === 'All Time' ? Story::where('status', $status->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 4')->count() : Story::where('status', $status->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 4')->count();

            // Calcola la percentuale rispetto al totale
            $yearPercentage = $totalStories > 0 ? ($yearTotal / $totalStories) * 100 : 0;
            $q1Percentage = $totalStories > 0 ? ($q1 / $totalStories) * 100 : 0;
            $q2Percentage = $totalStories > 0 ? ($q2 / $totalStories) * 100 : 0;
            $q3Percentage = $totalStories > 0 ? ($q3 / $totalStories) * 100 : 0;
            $q4Percentage = $totalStories > 0 ? ($q4 / $totalStories) * 100 : 0;

            // Aggiorna i totali
            $totals['year_total'] += $yearTotal;
            $totals['q1'] += $q1;
            $totals['q2'] += $q2;
            $totals['q3'] += $q3;
            $totals['q4'] += $q4;

            $reportByStatus[] = [
                'status' => $status->value,
                'year_total' => $yearTotal,
                'year_percentage' => $yearPercentage,
                'q1' => $q1,
                'q1_percentage' => $q1Percentage,
                'q2' => $q2,
                'q2_percentage' => $q2Percentage,
                'q3' => $q3,
                'q3_percentage' => $q3Percentage,
                'q4' => $q4,
                'q4_percentage' => $q4Percentage,
            ];
        }

        return [$reportByStatus, $totals]; // Restituisci anche i totali
    }



    /**
     * Funzione per determinare l'anno e i quarter disponibili
     */
    private function getYearAndQuarters($year)
    {
        $currentYear = Carbon::now()->year;
        $currentQuarter = Carbon::now()->quarter;

        // Se non viene passato un anno, visualizza "All Time" e considera tutti i dati disponibili
        if (!$year) {
            return ['All Time', [1, 2, 3, 4], null]; // Nessun errore, tutti i quarter sono disponibili
        }

        // Se l'anno passato è nel futuro, restituiamo un errore
        if ($year > $currentYear) {
            return [$year, [], 'Nessun dato disponibile per il futuro.'];
        }

        // Se l'anno è quello corrente, restituiamo solo i quarter fino al corrente
        $availableQuarters = $year == $currentYear ? range(1, $currentQuarter) : [1, 2, 3, 4];

        return [$year, $availableQuarters, null]; // Nessun errore
    }

    private function generateReportByUser($year, $availableQuarters)
    {
        $users = Story::with('user') // Carica la relazione con User
            ->select('user_id')
            ->distinct()
            ->get();

        // Variabile per contenere i totali degli utenti e per l'intero anno
        $reportByUser = [];

        // Variabile per il totale complessivo (come intero)
        $totalOverall = 0;

        // Calcolo dei totali per ogni quarter
        foreach ($availableQuarters as $quarter) {
            $reportByUser['q' . $quarter] = $this->calculateUserTotalsByQuarter($year, $quarter, $users, $totalOverall);
        }

        // Calcolo del totale annuo
        $reportByUser['year'] = $this->calculateUserTotalsByYear($year, $users, $totalOverall);

        // Restituisce sia i dettagli per gli utenti che il totale complessivo
        return [$reportByUser, $totalOverall];
    }

    private function calculateUserTotalsByQuarter($year, $quarter, $users, &$totalOverall)
    {
        $reportByUser = [];

        foreach ($users as $user) {
            $userName = $user->user->name ?? 'non assegnato'; // Recupera il nome utente

            // Inizializza i dati dell'utente per il quarter corrente
            $userData = [
                'user_id' => $userName,
                'total' => 0, // Totale inizializzato a 0
            ];

            foreach (StoryStatus::cases() as $status) {
                // Calcola il totale delle storie per utente e stato specifico nel quarter corrente
                $statusTotal = Story::where('user_id', $user->user_id)
                    ->where('status', $status->value)
                    ->whereRaw('EXTRACT(QUARTER FROM updated_at) = ?', [$quarter])
                    ->when($year !== 'All Time', function ($query) use ($year) {
                        return $query->whereYear('updated_at', $year);
                    })
                    ->count();

                // Aggiungi il totale per lo stato corrente
                $userData[$status->value . '_total'] = $statusTotal;

                // Aggiungi il totale di tutte le storie dell'utente per il quarter
                $userData['total'] += $statusTotal;

                // Aumenta il totale complessivo
                $totalOverall += $statusTotal;
            }

            $reportByUser[] = $userData;
        }

        return $reportByUser; // Restituisce i dati per tutti gli utenti per questo quarter
    }

    private function calculateUserTotalsByYear($year, $users, &$totalOverall)
    {
        $reportByUser = [];

        foreach ($users as $user) {
            $userName = $user->user->name ?? 'non assegnato'; // Recupera il nome utente

            // Inizializza i dati dell'utente per l'anno
            $userData = [
                'user_id' => $userName,
                'total' => 0, // Totale inizializzato a 0
            ];

            foreach (StoryStatus::cases() as $status) {
                // Calcola il totale delle storie per utente e stato specifico per l'intero anno
                $statusTotal = Story::where('user_id', $user->user_id)
                    ->where('status', $status->value)
                    ->when($year !== 'All Time', function ($query) use ($year) {
                        return $query->whereYear('updated_at', $year);
                    })
                    ->count();

                // Aggiungi il totale per lo stato corrente
                $userData[$status->value . '_total'] = $statusTotal;

                // Aggiungi il totale di tutte le storie dell'utente per l'anno
                $userData['total'] += $statusTotal;

                // Aumenta il totale complessivo
                $totalOverall += $statusTotal;
            }

            $reportByUser[] = $userData;
        }

        return $reportByUser; // Restituisce i dati per tutti gli utenti per l'anno
    }
}
