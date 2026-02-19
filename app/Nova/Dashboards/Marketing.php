<?php

namespace App\Nova\Dashboards;

use App\Models\Customer;
use App\Models\Quote;
use Laravel\Nova\Dashboard;
use Webmapp\KanbanCard\KanbanCard;

class Marketing extends Dashboard
{
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
                ->limitPerColumn(5)
                ->deniedToUpdateStatus(['editor', 'developer'])
                ->collapsible() 
                ->with(['customer'])
                ->resourceUri('quotes')
                ->filterBy('customer_id', Customer::class, 'name')
                ->searchBy(['customer.name', 'title'])
                ->title('title')
                ->subtitle('customer.name')
                ->displayFields([
                    'customer.name' => __('Customer'),
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
                    ['value' => 'closed won offer', 'label' => __('Closed Won Offer'), 'color' => '#047857'],
                    ['value' => 'closed lost offer', 'label' => __('Closed Lost Offer'), 'color' => '#DC2626'],
                ])
                ->width('full'),
        ];
    }
}
