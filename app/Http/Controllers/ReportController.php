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

        return view('reports.index', compact('tab1Type', 'tab2Status', 'tab2StatusTotals', 'year', 'availableQuarters', 'tab3DevStatus', 'developers', 'tab4StatusDev', 'tab5CustomerStatus', 'tab6StatusCustomer', 'tab7TagCustomer', 'tab8CustomerTag', 'tab9TagType'));
    }
    /**
     * Genera il report per Tipo di Storia
     */
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
    /**
     * Genera il report per Stato di Storia
     */
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

    private function calculateRowData($year, $firstColumnCells, $thead, $nameFn, $queryFn, $quarter = null)
    {
        $rows = [];
        foreach ($firstColumnCells as $cell) {
            $row = [];
            foreach ($thead as $column) {
                if ($column === '') {
                    $row[] = $nameFn($cell, $column);
                } elseif ($column === 'totale') {
                    $totalPerUser = array_sum(array_slice($row, 1)); // Somma dei valori per gli stati
                    $row[] = $totalPerUser;
                } else {
                    $query = $queryFn($cell, $column);

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
                }
            }
            $rows[] = $row;
        }
        usort($rows, function ($a, $b) {
            return $b[count($a) - 1] <=> $a[count($a) - 1]; // Ordina in base all'ultima colonna (totale)
        });

        return $rows; // Restituisce un array di righe che segue l'ordine di thead
    }


    private function tab3DevStatus($year, $availableQuarters, $developers)
    {
        $queryFn = function ($cell, $column) {
            return   Ticket::where('user_id', $cell->id)
                ->where('status', $column);
        };
        $nameFn = function ($cell) {
            return $cell->name;
        };
        $thead = array_merge([''], StoryStatus::values(), ['totale']);
        $firstColumnCells = $developers;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $nameFn, $queryFn);
    }
    private function tab5CustomerStatus($year, $availableQuarters, $customers)
    {
        $queryFn = function ($cell, $column) {
            return   Ticket::where('creator_id', $cell->id)
                ->where('status', $column);
        };
        $nameFn = function ($cell) {
            return $cell->name;
        };
        $thead = array_merge([''], StoryStatus::values(), ['totale']);
        $firstColumnCells = $customers;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $nameFn, $queryFn);
    }
    private function tab7TagCustomer($year, $availableQuarters, $tags, $customers)
    {
        $queryFn = function ($tag, $column) {
            // Query per contare quante storie hanno il tag specificato e lo stato specificato
            return Ticket::whereHas('tags', function ($query) use ($tag) {
                $query->where('tags.id', $tag->id); // Filtra per il tag specifico
            })
                ->whereHas('creator', function ($q) use ($column) {
                    $q->where('name', $column); // Filtra per il nome dell'utente nel campo 'column'
                });
        };
        $nameFn = function ($cell) {
            return $cell->name;
        };
        $thead = array_merge([''], $customers->pluck('name')->toArray(), ['totale']);
        $firstColumnCells = $tags;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $nameFn, $queryFn);
    }

    private function tab4StatusDev($year, $availableQuarters, $developers)
    {
        $thead = array_merge([''], $developers->pluck('name')->toArray(), ['totale']);
        $queryFn = function ($cell, $column) {
            return     Ticket::where('status', $cell)
                ->whereHas('user', function ($q) use ($column) {
                    $q->where('name', $column); // Filtra per il nome dell'utente nel campo 'column'
                });
        };
        $nameFn = function ($cell, $column) {
            return $cell ?? 'non assegnato';
        };
        $firstColumnCells = StoryStatus::values();

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $nameFn, $queryFn);
    }

    private function tab6StatusCustomer($year, $availableQuarters, $customer)
    {
        $thead = array_merge([''], $customer->pluck('name')->toArray(), ['totale']);
        $queryFn = function ($cell, $column) {
            return     Ticket::where('status', $cell)
                ->whereHas('creator', function ($q) use ($column) {
                    $q->where('name', $column); // Filtra per il nome dell'utente nel campo 'column'
                });
        };
        $nameFn = function ($cell, $column) {
            return $cell ?? 'non assegnato';
        };
        $firstColumnCells = StoryStatus::values();

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $nameFn, $queryFn);
    }
    private function tab8CustomerTag($year, $availableQuarters, $tags, $customers)
    {
        $thead = array_merge([''], $tags->pluck('name')->toArray(), ['totale']);
        $queryFn = function ($cell, $column) use ($year) {
            return Ticket::whereNotNull('creator_id')
                ->whereHas('creator', function ($q) use ($cell, $column) {
                    $q->where('name', $cell->name); // Filtra per il nome dell'utente nel campo 'column'
                }) // Filtra per lo stato specifico
                ->whereHas('tags', function ($query) use ($cell, $column) {
                    $query->where('tags.name', $column); // Filtra per il nome del tag
                })
            ;
        };
        $nameFn = function ($cell, $column) {
            return $cell->name ?? 'non assegnato';
        };
        $firstColumnCells = $customers;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $nameFn, $queryFn);
    }

    private function tab9TagType($year, $availableQuarters, $tags, $customers)
    {
        $thead = array_merge([''], StoryType::values(), ['totale']);
        $queryFn = function ($cell, $column) use ($year) {
            return Ticket::whereNotNull('creator_id')
                ->whereHas('tags', function ($query) use ($cell, $column) {
                    $query->where('tags.name', $cell->name); // Filtra per il nome del tag
                })
                ->where('type', $column);
        };
        $nameFn = function ($cell, $column) {
            return $cell->name ?? 'non assegnato';
        };
        $firstColumnCells = $tags;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $nameFn, $queryFn);
    }




    private function generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $nameFn, $queryFn)
    {
        // Variabile per contenere i totali degli utenti e per l'intero anno
        $quarterReport = [];
        $quarterReport['thead'] = $thead;
        $quarterReport['tbody'] = [];

        $tbody['year'] = $this->calculateRowData($year, $firstColumnCells, $thead, $nameFn, $queryFn);
        foreach ($availableQuarters as $quarter) {
            $tbody['q' . $quarter] = $this->calculateRowData($year, $firstColumnCells, $thead, $nameFn, $queryFn, $quarter);
        }
        $quarterReport['tbody'] =   $tbody;

        return $quarterReport;
    }
}
