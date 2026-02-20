<?php

namespace App\Nova\Dashboards;

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
                ->deniedToUpdateStatus(['editor', 'developer'])
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
                ->columns([
                    ['value' => 'new', 'label' => __('New')],
                    ['value' => 'to present', 'label' => __('To Present'), 'color' => '#F59E0B'],
                    ['value' => 'sent', 'label' => __('Sent'), 'color' => '#06B6D4'],
                    ['value' => 'presented', 'label' => __('Presented'), 'color' => '#8B5CF6'],
                    ['value' => 'waiting for order', 'label' => __('Waiting For Order'), 'color' => '#F97316'],
                    ['value' => 'cold', 'label' => __('Cold'), 'color' => '#6B7280'],
                    ['value' => 'closed won', 'label' => __('Closed Won'), 'color' => '#10B981'],
                    ['value' => 'closed lost', 'label' => __('Closed Lost'), 'color' => '#EF4444'],
                    ['value' => 'partially paid', 'label' => __('Partially Paid'), 'color' => '#14B8A6'],
                    ['value' => 'paid', 'label' => __('Paid'), 'color' => '#059669'],
                ])
        ];
    }
}
