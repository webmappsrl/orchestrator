<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Task extends Model
{
    use HasFactory;

    public const STATUS_TODO = 'todo';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'quote_id',
        'title',
        'notes',
        'due_date',
        'status',
        'completed_at',
        'creator_id',
        'assignee_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_TODO,
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Assegnatario del task, salvato in `assignee_id` e indipendente
     * dall'owner della Quote (oc:8722): l'owner conta solo come default
     * alla creazione, vedi defaultAssigneeId().
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Assegnatario di default (oc:8722, review del 14/10/2026): l'owner
     * della Quote; se manca, o non ha un ruolo che accede ai task, l'utente
     * che agisce. Unico punto della regola: lo usano l'hook di creazione,
     * il campo Nova precompilato, la riassegnazione alla cancellazione di
     * un utente e il popup di chiusura di oc:8730.
     */
    public static function defaultAssigneeId(?Quote $quote, ?User $actor): ?int
    {
        $owner = $quote?->user;

        if ($owner !== null && $owner->canAccessTasks()) {
            return $owner->id;
        }

        return $actor?->id;
    }

    /**
     * Prepende una nota in cima a `notes`, con autore e timestamp, mirror
     * del comportamento reale di Story::addDevNote() (che nonostante il
     * nome storico "prepende", non accoda — la nota più recente resta
     * sempre in cima). Mai sovrascrittura del contenuto esistente.
     */
    public function appendNote(string $note, bool $persist = true): void
    {
        $sender = auth()->user();
        $divider = "<div style='height: 2px; background-color: #e2e8f0; margin: 20px 0;'></div>";
        $style = "style='background-color: #f8f9fa; border-left: 4px solid #6c757d; padding: 10px 20px;'";

        $formatted = $sender->name . ' ha aggiunto una nota il: ' . now()->format('d-m-Y H:i')
            . "\n <div $style> <p>" . $note . ' </p> </div>' . $divider;

        $this->notes = $formatted . ($this->notes ?? '');

        if ($persist) {
            $this->save();
        }
    }

    /**
     * Task di un utente: quelli assegnati a lui oppure creati da lui
     * (oc:8722). L'owner della Quote non conta più: i task della sua
     * trattativa assegnati e creati da altri restano visibili nel tab Task
     * della Quote.
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('assignee_id', $user->id)->orWhere('creator_id', $user->id);
        });
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_TODO)
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeDueToday($query)
    {
        return $query->where('status', self::STATUS_TODO)
            ->whereDate('due_date', now()->toDateString());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_TODO)
            ->whereDate('due_date', '>', now()->toDateString());
    }

    public function scopeCompletedStatus($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    protected static function booted()
    {
        static::creating(function (Task $task) {
            if ($task->creator_id === null && auth()->check()) {
                $task->creator_id = auth()->id();
            }

            if ($task->assignee_id === null) {
                $task->assignee_id = self::defaultAssigneeId($task->quote, auth()->user());
            }
        });

        static::saving(function (Task $task) {
            if (!$task->isDirty('status')) {
                return;
            }

            if ($task->status === self::STATUS_COMPLETED) {
                $task->completed_at = $task->completed_at ?? now();
            } else {
                $task->completed_at = null;
            }
        });
    }
}
