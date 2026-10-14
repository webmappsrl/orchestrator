<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use App\Policies\TaskPolicy;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Auth\Impersonatable;
use Laravel\Sanctum\HasApiTokens;
use Wm\WmPackage\Models\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Impersonatable, Notifiable;

    private const NOVA_ACCESS_PERMISSION = 'access-nova';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'roles',
        'slack_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'roles' => AsEnumCollection::class.':'.UserRole::class,
    ];

    protected static function booted()
    {
        // I task di un utente cancellato si riassegnano con la regola del
        // default (oc:8722): owner della Quote, altrimenti il creatore del
        // task. Se il creatore è lo stesso utente e la Quote non ha owner,
        // il task resta senza assegnatario (nullOnDelete).
        static::deleting(function (User $user) {
            $tasks = $user->assignedTasks()->with(['quote.user', 'creator'])->get();

            foreach ($tasks as $task) {
                $creator = $task->creator?->is($user) ? null : $task->creator;
                $task->assignee_id = Task::defaultAssigneeId($task->quote, $creator);
                $task->saveQuietly();
            }
        });
    }

    /**
     * La cancellazione gira in una transazione: se la DELETE fallisce (per
     * esempio per la FK di quotes.user_id), si annulla anche la
     * riassegnazione dei task fatta nell'hook `deleting`. Nova non apre
     * transazioni quando cancella (Laravel\Nova\Jobs\DeleteResources).
     */
    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        if ($roles instanceof UserRole) {
            // `roles` è nullable: un utente senza ruoli non ha nessun ruolo.
            return $this->roles?->contains($roles) ?? false;
        }
        return parent::hasRole($roles, $guard);
    }

    /**
     * Vero se l'utente ha almeno uno dei ruoli che accedono ai Task
     * (TaskPolicy::ROLES).
     */
    public function canAccessTasks(): bool
    {
        foreach (TaskPolicy::ROLES as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Utenti con almeno uno dei ruoli che accedono ai Task
     * (TaskPolicy::ROLES): sono gli unici assegnatari ammessi.
     */
    public function scopeWithTaskAccess($query)
    {
        return $query->where(function ($q) {
            foreach (TaskPolicy::ROLES as $role) {
                $q->orWhereJsonContains('roles', $role->value);
            }
        });
    }

    public function can($abilities, $arguments = []): bool
    {
        if (is_string($abilities) && $abilities === self::NOVA_ACCESS_PERMISSION) {
            return true;
        }

        return parent::can($abilities, $arguments);
    }

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    public function participatingStories()
    {
        return $this->belongsToMany(Story::class, 'story_participants');
    }

    public function customerStories()
    {
        return $this->hasMany(Story::class, 'creator_id');
    }

    public function testerStories()
    {
        return $this->hasMany(Story::class, 'tester_id');
    }

    public function epics()
    {
        return $this->hasMany(Epic::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /**
     * Customers owned by this user (user manages the customer).
     */
    public function ownedCustomers()
    {
        return $this->hasMany(Customer::class, 'user_id');
    }

    /**
     * Customer this user is associated with (when user has Customer role).
     */
    public function associatedCustomer()
    {
        return $this->hasOne(Customer::class, 'associated_user_id');
    }

    /**
     * Determine if the user can impersonate another user.
     *
     * @return bool
     */
    public function canImpersonate()
    {
        return $this->hasRole(UserRole::Admin);
    }

    /**
     * Determine if the user can be impersonated.
     *
     * @return bool
     */
    public function canBeImpersonated()
    {
        return true;
    }

    /**
     * define if user has breadcrumbs
     */
    public function wantsBreadcrumbs(): bool
    {
        return ! $this->hasRole(UserRole::Customer);
    }

    /**
     * Define the initial nova path for the logged user
     */
    public function initialPath(): string
    {
        if ($this->hasRole(UserRole::Customer)) {
            return '/resources/story-showed-by-customers';
        } else {
            return '/dashboards/kanban';
        }
    }

    public function authorizedApps(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(App::class, 'user_app', 'user_id', 'app_id');
    }

    public function favorite($object): void
    {
        if (! $this->hasFavorited($object)) {
            \Illuminate\Support\Facades\DB::table('favorites')->insert([
                'user_id'           => $this->getKey(),
                'favoriteable_id'   => $object->getKey(),
                'favoriteable_type' => $object->getMorphClass(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    public function unfavorite($object): void
    {
        \Illuminate\Support\Facades\DB::table('favorites')
            ->where('user_id', $this->getKey())
            ->where('favoriteable_id', $object->getKey())
            ->where('favoriteable_type', $object->getMorphClass())
            ->delete();
    }

    public function hasFavorited($object): bool
    {
        return \Illuminate\Support\Facades\DB::table('favorites')
            ->where('user_id', $this->getKey())
            ->where('favoriteable_id', $object->getKey())
            ->where('favoriteable_type', $object->getMorphClass())
            ->exists();
    }

    public function getFavoriteItems(string $class): \Illuminate\Database\Eloquent\Builder
    {
        $favoritedIds = \Illuminate\Support\Facades\DB::table('favorites')
            ->where('user_id', $this->getKey())
            ->where('favoriteable_type', (new $class)->getMorphClass())
            ->pluck('favoriteable_id');

        return $class::whereIn((new $class)->getKeyName(), $favoritedIds);
    }
}
