<?php

namespace App\Nova\Dashboards;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Nova\Dashboard;
use App\Nova\Kanban\SalesQuoteColumnAggregator;
use Webmapp\KanbanCard\KanbanCard;

class Sales extends Dashboard
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

    public function name()
    {
        return __('Sales');
    }

    public function uriKey()
    {
        return 'sales';
    }

    /**
     * Get the cards for the dashboard.
     */
    public function cards()
    {
        return [
            (new KanbanCard)
                ->model(Quote::class, 'status')
                ->with(['customer', 'user', 'products', 'recurringProducts'])
                ->deniedToUpdateStatusForRoles([UserRole::Editor, UserRole::Developer])
                ->allowedToUpdateStatusForRoles([UserRole::Admin, UserRole::Manager])
                ->resourceUri('quotes')
                ->aggregateColumnsUsing(SalesQuoteColumnAggregator::class)
                ->filterAndSearchBy(
                    'customer_id',
                    Customer::class,
                    'full_name',
                    ['customer.full_name', 'customer.name', 'title', 'user.name'],
                    fn ($q) => $q->whereHas('quotes'),
                    [['user_id', User::class, 'name', fn ($q) => $q->whereHas('quotes')]]
                )
                ->toolbarTitle(__('Sales Kanban View'))
                ->toolbarLabel(__('Search or select customer:'))
                ->title('title')
                ->subtitle('customer.full_name')
                ->displayFields([
                    'customer.full_name' => __('Customer'),
                    'net_total' => __('Total'),
                ])
                ->priorityField('priority')
                ->enableIntraColumnReorder(true)
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
        ];
    }
}
