<?php

namespace App\Http\Controllers;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Story as Ticket;
use App\Models\Tag;
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
        $customers = $this->getCustomers();
        $tags = $this->getTags();

        $tab1Type = $this->tab1Type($year, $availableQuarters);
        [$tab2Status, $tab2StatusTotals] = $this->tab2Status($year, $availableQuarters); // Ora include i totali
        $tab3DevStatus = $this->tab3DevStatus($year, $availableQuarters, $developers);
        $tab4StatusDev = $this->tab4StatusDev($year, $availableQuarters, $developers);
        $tab5CustomerStatus = $this->tab5CustomerStatus($year, $availableQuarters, $customers);
        $tab6StatusCustomer = $this->tab6StatusCustomer($year, $availableQuarters, $customers);
        $tab7TagCustomer = $this->tab7TagCustomer($year, $availableQuarters, $tags, $customers);
        $tab8CustomerTag = $this->tab8CustomerTag($year, $availableQuarters, $tags, $customers);
        $tab9TagType = $this->tab9TagType($year, $availableQuarters, $tags, $customers);
        $tab10DevType = $this->tab10DevType($year, $availableQuarters, $developers);

        return view('reports.index', compact('tab1Type', 'tab2Status', 'tab2StatusTotals', 'year', 'availableQuarters', 'tab3DevStatus', 'developers', 'tab4StatusDev', 'tab5CustomerStatus', 'tab6StatusCustomer', 'tab7TagCustomer', 'tab8CustomerTag', 'tab9TagType', 'tab10DevType'));
    }
    private function generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn)
    {
        $quarterReport = [];
        $quarterReport['thead'] = $thead;
        $quarterReport['tbody'] = [];
        $tbody['year'] = $this->calculateRowData($year, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
        foreach ($availableQuarters as $quarter) {
            $tbody['q' . $quarter] = $this->calculateRowData($year, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn, $quarter);
        }
        $quarterReport['tbody'] =   $tbody;

        return $quarterReport;
    }

    private function getYearAndQuarters($year)
    {
        $currentYear = Carbon::now()->year;
        $currentQuarter = Carbon::now()->quarter;
        if (!$year) {
            return ['All Time', [1, 2, 3, 4], null]; // Nessun errore, tutti i quarter sono disponibili
        }
        if ($year > $currentYear) {
            return [$year, [], 'Nessun dato disponibile per il futuro.'];
        }
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
    private function getCustomers()
    {
        return Ticket::whereNotNull('creator_id')
            ->whereHas('creator', function ($query) {
                $query->whereJsonContains('roles', UserRole::Customer); // Filtra utenti con il ruolo 'Customer'
            })
            ->selectRaw('creator_id, COUNT(*) as story_count') // Seleziona il creator_id e conta le storie
            ->groupBy('creator_id') // Raggruppa per creator_id
            ->orderByDesc('story_count') // Ordina per il numero di storie in modo decrescente
            ->limit(10) // Limita ai primi 10
            ->with('creator') // Precarica il creatore
            ->get()
            ->pluck('creator') // Ottiene solo i creatori
            ->unique('id'); // Rimuovi eventuali duplicati, se ce ne sono

    }
    private function getTags()
    {
        return Tag::withCount('tagged') // Conta quante storie sono associate a ciascun tag
            ->orderBy('tagged_count', 'desc') // Ordina per frequenza di utilizzo
            ->limit(10) // Limita ai primi 10 tag più usati
            ->get();
    }

    private function calculateRowData($year, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn, $quarter = null)
    {
        $rows = [];
        $columnSums = array_fill(0, count($thead), 0); // Inizializza array per i totali delle colonne

        foreach ($firstColumnCells as $indexRowObj) {
            $row = [];
            foreach ($thead as $index => $indexColumnObj) {
                if ($indexColumnObj === '') {
                    $row[] = $firstColumnNameFn($indexRowObj, $indexColumnObj);
                } elseif ($indexColumnObj === 'totale') {
                    $row[] = array_sum(array_slice($row, 1)); // Somma delle celle precedenti nella riga
                } else {
                    $query = $cellQueryFn($indexRowObj, $indexColumnObj);
                    if ($quarter) {
                        $query->whereRaw('EXTRACT(QUARTER FROM updated_at) = ?', [$quarter]);
                    }
                    if ($year !== 'All Time') {
                        $query->whereYear('updated_at', $year);
                    }
                    $statusTotal = $query->count();
                    $row[] = $statusTotal;

                    // Aggiorna il totale della colonna corrente
                    $columnSums[$index] += $statusTotal;
                }
            }
            $rows[] = $row;
        }

        // Ordina le righe in base all'ultima colonna (totale)
        usort($rows, function ($a, $b) {
            return $b[count($a) - 1] <=> $a[count($a) - 1];
        });

        // Aggiungi la riga dei totali alla fine
        $totalsRow = ['Totale']; // La prima cella della riga è 'Totale'
        foreach ($thead as $index => $indexColumnObj) {
            if ($indexColumnObj === '') {
                continue; // Salta la prima cella (già 'Totale')
            } elseif ($indexColumnObj === 'totale') {
                $totalsRow[] = array_sum(array_slice($columnSums, 1)); // Totale finale (somma delle somme delle colonne)
            } else {
                $totalsRow[] = $columnSums[$index]; // Aggiungi la somma verticale della colonna
            }
        }

        $rows[] = $totalsRow; // Aggiungi la riga dei totali alla fine delle righe

        return $rows;
    }


    private function tab1Type($year, $availableQuarters)
    {
        $totalStories = $year === 'All Time' ? Ticket::count() : Ticket::whereYear('updated_at', $year)->count();

        $tab1Type = [];
        foreach (StoryType::cases() as $type) {
            $yearTotal = $year === 'All Time' ? Ticket::where('type', $type->value)->count() : Ticket::where('type', $type->value)->whereYear('updated_at', $year)->count();
            $q1 = $year === 'All Time' ? Ticket::where('type', $type->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 1')->count() : Ticket::where('type', $type->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 1')->count();
            $q2 = $year === 'All Time' ? Ticket::where('type', $type->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 2')->count() : Ticket::where('type', $type->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 2')->count();
            $q3 = $year === 'All Time' ? Ticket::where('type', $type->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 3')->count() : Ticket::where('type', $type->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 3')->count();
            $q4 = $year === 'All Time' ? Ticket::where('type', $type->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 4')->count() : Ticket::where('type', $type->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 4')->count();

            // Calcola la percentuale rispetto al totale
            $yearPercentage = $totalStories > 0 ? ($yearTotal / $totalStories) * 100 : 0;
            $q1Percentage = $totalStories > 0 ? ($q1 / $totalStories) * 100 : 0;
            $q2Percentage = $totalStories > 0 ? ($q2 / $totalStories) * 100 : 0;
            $q3Percentage = $totalStories > 0 ? ($q3 / $totalStories) * 100 : 0;
            $q4Percentage = $totalStories > 0 ? ($q4 / $totalStories) * 100 : 0;

            $tab1Type[] = [
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

        return $tab1Type;
    }

    private function tab2Status($year, $availableQuarters)
    {
        $totalStories = $year === 'All Time' ? Ticket::count() : Ticket::whereYear('updated_at', $year)->count();

        $tab2Status = [];
        $totals = [
            'year_total' => 0,
            'q1' => 0,
            'q2' => 0,
            'q3' => 0,
            'q4' => 0,
        ];

        foreach (StoryStatus::cases() as $status) {
            $yearTotal = $year === 'All Time' ? Ticket::where('status', $status->value)->count() : Ticket::where('status', $status->value)->whereYear('updated_at', $year)->count();
            $q1 = $year === 'All Time' ? Ticket::where('status', $status->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 1')->count() : Ticket::where('status', $status->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 1')->count();
            $q2 = $year === 'All Time' ? Ticket::where('status', $status->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 2')->count() : Ticket::where('status', $status->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 2')->count();
            $q3 = $year === 'All Time' ? Ticket::where('status', $status->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 3')->count() : Ticket::where('status', $status->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 3')->count();
            $q4 = $year === 'All Time' ? Ticket::where('status', $status->value)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 4')->count() : Ticket::where('status', $status->value)->whereYear('updated_at', $year)->whereRaw('EXTRACT(QUARTER FROM updated_at) = 4')->count();

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

            $tab2Status[] = [
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

        return [$tab2Status, $totals]; // Restituisci anche i totali
    }

    private function tab3DevStatus($year, $availableQuarters, $developers)
    {
        $cellQueryFn = function ($indexRowObj, $indexColumnObj) {
            return   Ticket::where('user_id', $indexRowObj->id)
                ->where('status', $indexColumnObj);
        };
        $firstColumnNameFn = function ($indexRowObj) {
            return $indexRowObj->name;
        };
        $thead = array_merge([''], StoryStatus::values(), ['totale']);
        $firstColumnCells = $developers;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
    }

    private function tab4StatusDev($year, $availableQuarters, $developers)
    {
        $cellQueryFn = function ($indexRowObj, $indexColumnObj) {
            return     Ticket::where('status', $indexRowObj)
                ->whereHas('user', function ($q) use ($indexColumnObj) {
                    $q->where('name', $indexColumnObj); // Filtra per il nome dell'utente nel campo 'column'
                });
        };
        $firstColumnNameFn = function ($indexRowObj, $indexColumnObj) {
            return $indexRowObj ?? 'non assegnato';
        };
        $thead = array_merge([''], $developers->pluck('name')->toArray(), ['totale']);
        $firstColumnCells = StoryStatus::values();

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
    }

    private function tab5CustomerStatus($year, $availableQuarters, $customers)
    {
        $cellQueryFn = function ($indexRowObj, $indexColumnObj) {
            return   Ticket::where('creator_id', $indexRowObj->id)
                ->where('status', $indexColumnObj);
        };
        $firstColumnNameFn = function ($indexRowObj) {
            return $indexRowObj->name;
        };
        $thead = array_merge([''], StoryStatus::values(), ['totale']);
        $firstColumnCells = $customers;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
    }

    private function tab6StatusCustomer($year, $availableQuarters, $customer)
    {
        $cellQueryFn = function ($indexRowObj, $indexColumnObj) {
            return     Ticket::where('status', $indexRowObj)
                ->whereHas('creator', function ($q) use ($indexColumnObj) {
                    $q->where('name', $indexColumnObj); // Filtra per il nome dell'utente nel campo 'column'
                });
        };
        $firstColumnNameFn = function ($indexRowObj, $indexColumnObj) {
            return $indexRowObj ?? 'non assegnato';
        };
        $thead = array_merge([''], $customer->pluck('name')->toArray(), ['totale']);
        $firstColumnCells = StoryStatus::values();

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
    }

    private function tab7TagCustomer($year, $availableQuarters, $tags, $customers)
    {
        $cellQueryFn = function ($tag, $indexColumnObj) {
            // Query per contare quante storie hanno il tag specificato e lo stato specificato
            return Ticket::whereHas('tags', function ($query) use ($tag) {
                $query->where('tags.id', $tag->id); // Filtra per il tag specifico
            })
                ->whereHas('creator', function ($q) use ($indexColumnObj) {
                    $q->where('name', $indexColumnObj); // Filtra per il nome dell'utente nel campo 'column'
                });
        };
        $firstColumnNameFn = function ($indexRowObj) {
            return $indexRowObj->name;
        };
        $thead = array_merge([''], $customers->pluck('name')->toArray(), ['totale']);
        $firstColumnCells = $tags;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
    }

    private function tab8CustomerTag($year, $availableQuarters, $tags, $customers)
    {
        $cellQueryFn = function ($indexRowObj, $indexColumnObj) use ($year) {
            return Ticket::whereNotNull('creator_id')
                ->whereHas('creator', function ($q) use ($indexRowObj, $indexColumnObj) {
                    $q->where('name', $indexRowObj->name); // Filtra per il nome dell'utente nel campo 'column'
                }) // Filtra per lo stato specifico
                ->whereHas('tags', function ($query) use ($indexRowObj, $indexColumnObj) {
                    $query->where('tags.name', $indexColumnObj); // Filtra per il nome del tag
                })
            ;
        };
        $firstColumnNameFn = function ($indexRowObj, $indexColumnObj) {
            return $indexRowObj->name ?? 'non assegnato';
        };
        $thead = array_merge([''], $tags->pluck('name')->toArray(), ['totale']);
        $firstColumnCells = $customers;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
    }

    private function tab9TagType($year, $availableQuarters, $tags, $customers)
    {
        $cellQueryFn = function ($indexRowObj, $indexColumnObj) use ($year) {
            return Ticket::whereNotNull('creator_id')
                ->whereHas('tags', function ($query) use ($indexRowObj, $indexColumnObj) {
                    $query->where('tags.name', $indexRowObj->name); // Filtra per il nome del tag
                })
                ->where('type', $indexColumnObj);
        };
        $firstColumnNameFn = function ($indexRowObj, $indexColumnObj) {
            return $indexRowObj->name ?? 'non assegnato';
        };
        $thead = array_merge([''], StoryType::values(), ['totale']);
        $firstColumnCells = $tags;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
    }

    private function tab10DevType($year, $availableQuarters, $developers)
    {
        $cellQueryFn = function ($indexRowObj, $indexColumnObj) {
            return   Ticket::where('user_id', $indexRowObj->id)
                ->where('type', $indexColumnObj);
        };
        $firstColumnNameFn = function ($indexRowObj) {
            return $indexRowObj->name;
        };
        $thead = array_merge([''], StoryType::values(), ['totale']);
        $firstColumnCells = $developers;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
    }
}
