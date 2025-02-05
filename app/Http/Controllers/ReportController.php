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
    const ALL_TIME = "All Time";
    const NO_DATA = 'Nessun dato disponibile';
    const NOT_ASSIGNED = 'non assegnato';
    const LAST_COLUMN_VALUE = 'totale';
    const LAST_COLUMN_LABEL = 'Totale';
    const SQL_PREFIX_FOR_EXTRACTING_QUARTER = 'EXTRACT(QUARTER FROM updated_at)';
    const REGEX_FOR_EXTRACTING_HOURS = '/(\d+)\s*\((\d+\.?\d*)\)/';
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
            return [self::ALL_TIME, [1, 2, 3, 4], null]; // Nessun errore, tutti i quarter sono disponibili
        }
        if ($year > $currentYear) {
            return [$year, [], self::NO_DATA];
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
        $columnHours = array_fill(0, count($thead), 0); // Inizializza array per i totali delle colonne

        foreach ($firstColumnCells as $indexRowObj) {
            $row = [];
            foreach ($thead as $index => $indexColumnObj) {
                if ($indexColumnObj === '') {
                    $row[] = $firstColumnNameFn($indexRowObj, $indexColumnObj);
                } elseif ($indexColumnObj === self::LAST_COLUMN_VALUE) {
                    $precedentCells = array_slice($row, 1);
                    $counts = [];
                    $hours = [];
                    foreach ($precedentCells as $cell) {
                        $counts[] = $this->extractCount($cell);
                        $hours[] = $this->extractHours($cell); 
                    }
                    $row[] = array_sum($counts) . " (" . round(array_sum($hours), 2) . ")";
                } else {
                    $query = $cellQueryFn($indexRowObj, $indexColumnObj);
                    if ($quarter) {
                        $query->whereRaw(self::SQL_PREFIX_FOR_EXTRACTING_QUARTER . ' = ?', [$quarter]);
                    }
                    if ($year !== self::ALL_TIME) {
                        $query->whereYear('updated_at', $year);
                    }
                    $statusTotal = $query->count();
                    $statusHours = round($query->sum('hours'), 2);
                    $row[] = $statusTotal. " (".$statusHours.")";

                    // Aggiorna il totale della colonna corrente
                    $columnSums[$index] += $statusTotal;
                    $columnHours[$index] += $statusHours;
                }
            }
            $rows[] = $row;
        }

        $rows =$this->sortRowsByHours($rows);

        // Aggiungi la riga dei totali alla fine
        $totalsRow = [self::LAST_COLUMN_LABEL]; // La prima cella della riga è 'Totale'
        foreach ($thead as $index => $indexColumnObj) {
            if ($indexColumnObj === '') {
                continue; // Salta la prima cella (già 'Totale')
            } elseif ($indexColumnObj === self::LAST_COLUMN_VALUE) {
                $totalsRow[] = array_sum(array_slice($columnSums, 1)). " (". round(array_sum(array_slice($columnHours, 1)), 2).")"; // Totale finale (somma delle somme delle colonne)
            } else {
                $totalsRow[] = $columnSums[$index]. " (". $columnHours[$index].")"; // Aggiungi la somma verticale della colonna
            }
        }

        $rows[] = $totalsRow; // Aggiungi la riga dei totali alla fine delle righe

        return $rows;
    }

    private function sortRowsByHours($rows): array
    {
        usort($rows, function ($firstElement, $secondElement) {
            $hoursFirstElement = $this->extractHours($this->getLastColumnValue($firstElement));
            $hoursSecondElement = $this->extractHours($this->getLastColumnValue($secondElement));
            return $hoursSecondElement <=> $hoursFirstElement; 
        });
        return $rows;
    }

    private function extractHours($cell)
    {
        preg_match(self::REGEX_FOR_EXTRACTING_HOURS, $cell, $matches);
        return isset($matches[2]) ? floatval($matches[2]) : 0;
    }

    private function extractCount($cell)
    {
        preg_match(self::REGEX_FOR_EXTRACTING_HOURS, $cell, $matches);
        return isset($matches[1]) ? intval($matches[1]) : 0;
    }

    private function getLastColumnValue(array $columns)
    {
        return $columns[count($columns) - 1];
    }

    private function tab1Type($year, $availableQuarters)
    {
        $totalStories = $year === self::ALL_TIME ? Ticket::count() : Ticket::whereYear('updated_at', $year)->count();

        $tab1Type = [];
        foreach (StoryType::cases() as $type) {
            $yearTotal = $this->getTicketsCountBy('type', $type, $year);
            $firstQuarter = $this->getTicketsCountBy('type', $type, $year, 1);
            $secondQuarter = $this->getTicketsCountBy('type', $type, $year, 2);
            $thirdQuarter = $this->getTicketsCountBy('type', $type, $year, 3);
            $fourthQuarter = $this->getTicketsCountBy('type', $type, $year, 4);

            // Calcola la percentuale rispetto al totale
            $yearPercentage = $totalStories > 0 ? ($yearTotal / $totalStories) * 100 : 0;
            $firstQuarterPercentage = $totalStories > 0 ? ($firstQuarter / $totalStories) * 100 : 0;
            $secondQuarterPercentage = $totalStories > 0 ? ($secondQuarter / $totalStories) * 100 : 0;
            $thirdQuarterPercentage = $totalStories > 0 ? ($thirdQuarter / $totalStories) * 100 : 0;
            $fourthQuarterPercentage = $totalStories > 0 ? ($fourthQuarter / $totalStories) * 100 : 0;

            $totalHours = $this->getTicketsSumOfHoursBy('type', $type, $year);
            $firstQuarterHours = $this->getTicketsSumOfHoursBy('type', $type, $year, 1);
            $secondQuarterHours = $this->getTicketsSumOfHoursBy('type', $type, $year, 2);
            $thirdQuarterHours = $this->getTicketsSumOfHoursBy('type', $type, $year, 3);
            $fourthQuarterHours = $this->getTicketsSumOfHoursBy('type', $type, $year, 4);

            $tab1Type[] = [
                'type' => $type->value,
                'year_total' => $yearTotal. " (".$totalHours.")",
                'year_percentage' => $yearPercentage,
                'q1' => $firstQuarter. " (".$firstQuarterHours.")",
                'q1_percentage' => $firstQuarterPercentage,
                'q2' => $secondQuarter. " (".$secondQuarterHours.")",
                'q2_percentage' => $secondQuarterPercentage,
                'q3' => $thirdQuarter. " (".$thirdQuarterHours.")",
                'q3_percentage' => $thirdQuarterPercentage,
                'q4' => $fourthQuarter. " (".$fourthQuarterHours.")",
                'q4_percentage' => $fourthQuarterPercentage,
            ];
        }

        return $tab1Type;
    }

    private function tab2Status($year, $availableQuarters)
    {
        $totalStories = $year === self::ALL_TIME ? Ticket::count() : Ticket::whereYear('updated_at', $year)->count();

        $tab2Status = [];
        $totals = [
            'year_total' => 0,
            'q1' => 0,
            'q2' => 0,
            'q3' => 0,
            'q4' => 0,
        ];
        $hoursTotals = [
            'year_total' => 0,
            'q1' => 0,
            'q2' => 0,
            'q3' => 0,
            'q4' => 0,
        ];

        foreach (StoryStatus::cases() as $status) {
            $yearTotal = $this->getTicketsCountBy('status', $status, $year);
            $firstQuarter = $this->getTicketsCountBy('status', $status, $year, 1);
            $secondQuarter = $this->getTicketsCountBy('status', $status, $year, 2);
            $thirdQuarter = $this->getTicketsCountBy('status', $status, $year, 3);
            $fourthQuarter = $this->getTicketsCountBy('status', $status, $year, 4);

            // Calcola la percentuale rispetto al totale
            $yearPercentage = $totalStories > 0 ? ($yearTotal / $totalStories) * 100 : 0;
            $firstQuarterPercentage = $totalStories > 0 ? ($firstQuarter / $totalStories) * 100 : 0;
            $secondQuarterPercentage = $totalStories > 0 ? ($secondQuarter / $totalStories) * 100 : 0;
            $thirdQuarterPercentage = $totalStories > 0 ? ($thirdQuarter / $totalStories) * 100 : 0;
            $fourthQuarterPercentage = $totalStories > 0 ? ($fourthQuarter / $totalStories) * 100 : 0;

            // Aggiorna i totali
            $totals['year_total'] += $yearTotal;
            $totals['q1'] += $firstQuarter;
            $totals['q2'] += $secondQuarter;
            $totals['q3'] += $thirdQuarter;
            $totals['q4'] += $fourthQuarter;

            $totalHours = $this->getTicketsSumOfHoursBy('status', $status, $year);
            $firstQuarterHours = $this->getTicketsSumOfHoursBy('status', $status, $year, 1);
            $secondQuarterHours = $this->getTicketsSumOfHoursBy('status', $status, $year, 2);
            $thirdQuarterHours = $this->getTicketsSumOfHoursBy('status', $status, $year, 3);
            $fourthQuarterHours = $this->getTicketsSumOfHoursBy('status', $status, $year, 4);

            $hoursTotals['year_total'] += $totalHours;
            $hoursTotals['q1'] += $firstQuarterHours;
            $hoursTotals['q2'] += $secondQuarterHours;
            $hoursTotals['q3'] += $thirdQuarterHours;
            $hoursTotals['q4'] += $fourthQuarterHours;
                    
            $tab2Status[] = [
                'status' => $status->value,
                'year_total' => $yearTotal." (".$totalHours.")",
                'year_percentage' => $yearPercentage,
                'q1' => $firstQuarter." (".$firstQuarterHours.")",
                'q1_percentage' => $firstQuarterPercentage,
                'q2' => $secondQuarter." (".$secondQuarterHours.")",
                'q2_percentage' => $secondQuarterPercentage,
                'q3' => $thirdQuarter." (".$thirdQuarterHours.")",
                'q3_percentage' => $thirdQuarterPercentage,
                'q4' => $fourthQuarter." (".$fourthQuarterHours.")",
                'q4_percentage' => $fourthQuarterPercentage,
            ];
        }

        $totals['year_total'] = $totals['year_total']. " (". $hoursTotals['year_total'].")";
        $totals['q1'] = $totals['q1']. " (". $hoursTotals['q1'].")";
        $totals['q2'] = $totals['q2']. " (". $hoursTotals['q2'].")";
        $totals['q3'] = $totals['q3']. " (". $hoursTotals['q3'].")";
        $totals['q4'] = $totals['q4']. " (". $hoursTotals['q4'].")";

        return [$tab2Status, $totals]; // Restituisci anche i totali
    }

    private function getTicketsCountBy($nameOfElementToCheck = 'type', $elementToCheck, $year = self::ALL_TIME, $quarter = null)
    {

        $query = Ticket::where($nameOfElementToCheck, $elementToCheck->value);
        if($year !== self::ALL_TIME){
            $query->whereYear('updated_at', $year);
        }
        if($quarter){
            $query->whereRaw(self::SQL_PREFIX_FOR_EXTRACTING_QUARTER . ' = ?', [$quarter]);
        }
        return $query->count();
    }

    private function getTicketsSumOfHoursBy($nameOfElementToCheck = 'type', $elementToCheck, $year = self::ALL_TIME, $quarter = null)
    {
        $query = Ticket::where($nameOfElementToCheck, $elementToCheck->value);
        if($year !== self::ALL_TIME){
            $query->whereYear('updated_at', $year);
        }
        if($quarter){
            $query->whereRaw(self::SQL_PREFIX_FOR_EXTRACTING_QUARTER . ' = ?', [$quarter]);
        }
        return round($query->sum('hours'), 2);
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
        $thead = array_merge([''], StoryStatus::values(), [self::LAST_COLUMN_VALUE]);
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
            return $indexRowObj ?? self::NOT_ASSIGNED;
        };
        $thead = array_merge([''], $developers->pluck('name')->toArray(), [self::LAST_COLUMN_VALUE]);
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
        $thead = array_merge([''], StoryStatus::values(), [self::LAST_COLUMN_VALUE]);
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
            return $indexRowObj ?? self::NOT_ASSIGNED;
        };
        $thead = array_merge([''], $customer->pluck('name')->toArray(), [self::LAST_COLUMN_VALUE]);
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
        $thead = array_merge([''], $customers->pluck('name')->toArray(), [self::LAST_COLUMN_VALUE]);
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
            return $indexRowObj->name ?? self::NOT_ASSIGNED;
        };
        $thead = array_merge([''], $tags->pluck('name')->toArray(), [self::LAST_COLUMN_VALUE]);
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
            return $indexRowObj->name ?? self::NOT_ASSIGNED;
        };
        $thead = array_merge([''], StoryType::values(), [self::LAST_COLUMN_VALUE]);
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
        $thead = array_merge([''], StoryType::values(), [self::LAST_COLUMN_VALUE]);
        $firstColumnCells = $developers;

        return $this->generateQuarterReport($year, $availableQuarters, $firstColumnCells, $thead, $firstColumnNameFn, $cellQueryFn);
    }
}
