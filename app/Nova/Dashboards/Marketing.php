<?php

namespace App\Nova\Dashboards;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Nova\Dashboard;
use Webmapp\KanbanCard\KanbanCard;

class Marketing extends Dashboard
{
    /**
     * Determine if the user can see the dashboard (stessa policy del CRM: solo Admin e Manager).
     */
    public function authorizedToSee(Request $request): bool
    {
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager);
    }

    /**
     * Get the displayable name of the dashboard.
     */
    public function name()
    {
        return __('Marketing');
    }

    /**
     * Get the URI key of the dashboard.
     */
    public function uriKey()
    {
        return 'marketing';
    }

    /**
     * Get the cards for the dashboard.
     */
    public function cards()
    {
        return [
            (new KanbanCard)
                ->model(Quote::class, 'status')
                ->with(['customer', 'user'])
                ->deniedToUpdateStatusForRoles([UserRole::Editor, UserRole::Developer])
                ->allowedToUpdateStatusForRoles([UserRole::Admin, UserRole::Manager])
                ->resourceUri('quotes')
                ->filterAndSearchBy(
                    'customer_id',
                    Customer::class,
                    'full_name',
                    ['customer.full_name', 'customer.name', 'title', 'user.name'],
                    fn ($q) => $q->whereHas('quotes'),
                    [['user_id', User::class, 'name', fn ($q) => $q->whereHas('quotes')]]
                )
                ->title('title')
                ->subtitle('customer.full_name')
                ->displayFields([
                    'customer.full_name' => __('Customer'),
                    'total' => __('Total'),
                ])
                ->limitPerColumn(5)
                ->columns(
                    array_map(
                        fn (QuoteStatus $status) => [
                            'value' => $status->value,
                            'label' => $status->label(),
                            'color' => $status->color() ?: KanbanCard::DEFAULT_COLOR,
                        ],
                        QuoteStatus::cases()
                    )
                )
                // ->excludedColumns([QuoteStatus::Cold->value])  // exclude columns by value if necessary
        ];
    }
}
