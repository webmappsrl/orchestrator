<?php

namespace App\Http\Controllers;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\User;
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
        $developers = $this->getDevelopers();

        // Ottieni i report per Tipo e Stato tramite funzioni separate
        $reportByType = $this->generateReportByType($year, $availableQuarters);
        [$reportByStatus, $totals] = $this->generateReportByStatus($year, $availableQuarters); // Ora include i totali
        // Ottieni i report per Utente e somma totale
        [$reportByUser, $totalOverall] = $this->generateReportByUser($year, $availableQuarters, $developers);
        [$reportByStatusUser, $totalOverall2] = $this->generateReportByStatusUser($year, $availableQuarters, $developers);

        return view('reports.index', compact('reportByType', 'reportByStatus', 'totals', 'year', 'availableQuarters', 'reportByUser', 'totalOverall', 'developers', 'reportByStatusUser'));
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

    private function getDevelopers()
    {
        $developers = User::whereJsonContains('roles', UserRole::Developer)
            ->whereHas('stories')  // Verifica che l'utente abbia storie associate
            ->distinct()
            ->get();
        return $developers;
    }
    private function generateReportByUser($year, $availableQuarters, $developers)
    {
        // Variabile per contenere i totali degli utenti e per l'intero anno
        $reportByUser = [];
        $reportByUser['thead'] = array_merge(['nome'], StoryStatus::values(), ['totale']);
        $reportByUser['tbody'] = [];

        // Variabile per il totale complessivo (come intero)
        $totalOverall = 0;


        // Calcolo del totale annuo
        $tbody['year'] = $this->calculateUserTotalsByYear($year, $developers,  $reportByUser['thead'], $totalOverall);
        foreach ($availableQuarters as $quarter) {
            $tbody['q' . $quarter] = $this->calculateUserTotalsByQuarter($year, $quarter, $developers, $reportByUser['thead'], $totalOverall);
        }

        $reportByUser['tbody'] =   $tbody;


        // Restituisce sia i dettagli per gli utenti che il totale complessivo
        return [$reportByUser, $totalOverall];
    }


    private function calculateUserTotalsByQuarter($year, $quarter, $developers, $thead, &$totalOverall)
    {
        return $this->calculateUserTotals($year, $developers, $thead, $totalOverall, $quarter);
    }


    private function calculateUserTotalsByYear($year, $developers, $thead, &$totalOverall)
    {
        return $this->calculateUserTotals($year, $developers, $thead, $totalOverall);
    }


    private function calculateUserTotals($year, $developers, $thead, &$totalOverall, $quarter = null)
    {
        $rows = [];
        foreach ($developers as $developer) {
            $row = [];
            foreach ($thead as $column) {
                if ($column === 'nome') {
                    $row[] = $developer->name ?? 'non assegnato';
                } elseif ($column === 'totale') {
                    $totalPerUser = array_sum(array_slice($row, 1)); // Somma dei valori per gli stati
                    $row[] = $totalPerUser;
                } else {
                    $query = Story::where('user_id', $developer->id)
                        ->where('status', $column);

                    if ($quarter) {
                        // Filtra per quarter se fornito
                        $query->whereRaw('EXTRACT(QUARTER FROM updated_at) = ?', [$quarter]);
                    }

                    if ($year !== 'All Time') {
                        $query->whereYear('updated_at', $year);
                    }

                    $statusTotal = $query->count();

                    // Aggiungi il totale per lo stato corrente
                    $row[] = $statusTotal;

                    // Aggiungi al totale complessivo
                    $totalOverall += $statusTotal;
                }
            }
            $rows[] = $row;
        }
        usort($rows, function ($a, $b) {
            return $b[count($a) - 1] <=> $a[count($a) - 1]; // Ordina in base all'ultima colonna (totale)
        });

        return $rows; // Restituisce un array di righe che segue l'ordine di thead
    }

    private function calculateStatusUserTotalsByYear($year, $developers, $thead, &$totalOverall)
    {
        return $this->calculateStatusUserTotals($year, $developers, $thead, $totalOverall);
    }

    private function calculateStatusUserTotalsByQuarter($year, $quarter, $developers, $thead, &$totalOverall)
    {
        return $this->calculateStatusUserTotals($year, $developers, $thead, $totalOverall, $quarter);
    }
    private function calculateStatusUserTotals($year, $developers, $thead, &$totalOverall, $quarter = null)
    {
        $rows = [];
        $status = StoryStatus::values();
        foreach ($status as $stat) {
            $row = [];
            foreach ($thead as $column) {
                if ($column === 'status') {
                    $row[] = $stat ?? 'non assegnato';
                } elseif ($column === 'totale') {
                    $totalPerUser = array_sum(array_slice($row, 1)); // Somma dei valori per gli stati
                    $row[] = $totalPerUser;
                } else {
                    $query = Story::where('status', $stat)
                        ->whereHas('user', function ($q) use ($column) {
                            $q->where('name', $column); // Filtra per il nome dell'utente nel campo 'column'
                        });


                    if ($quarter) {
                        // Filtra per quarter se fornito
                        $query->whereRaw('EXTRACT(QUARTER FROM updated_at) = ?', [$quarter]);
                    }

                    if ($year !== 'All Time') {
                        $query->whereYear('updated_at', $year);
                    }

                    $statusTotal = $query->count();

                    // Aggiungi il totale per lo stato corrente
                    $row[] = $statusTotal;

                    // Aggiungi al totale complessivo
                    $totalOverall += $statusTotal;
                }
            }
            $rows[] = $row;
        }
        usort($rows, function ($a, $b) {
            return $b[count($a) - 1] <=> $a[count($a) - 1]; // Ordina in base all'ultima colonna (totale)
        });

        return $rows; // Restituisce un array di righe che segue l'ordine di thead
    }

    private function generateReportByStatusUser($year, $availableQuarters, $developers)
    {
        // Variabile per contenere i totali degli utenti e per l'intero anno
        $reportByUser = [];
        $developerNames = $developers->pluck('name')->toArray();

        $reportByUser['thead'] = array_merge(['status'], $developerNames, ['totale']);
        $reportByUser['tbody'] = [];

        // Variabile per il totale complessivo (come intero)
        $totalOverall = 0;


        // Calcolo del totale annuo
        $tbody['year'] = $this->calculateStatusUserTotalsByYear($year, $developers,  $reportByUser['thead'], $totalOverall);
        foreach ($availableQuarters as $quarter) {
            $tbody['q' . $quarter] = $this->calculateStatusUserTotalsByQuarter($year, $quarter, $developers, $reportByUser['thead'], $totalOverall);
        }

        $reportByUser['tbody'] =   $tbody;


        // Restituisce sia i dettagli per gli utenti che il totale complessivo
        return [$reportByUser, $totalOverall];
    }
}
