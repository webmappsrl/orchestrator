<?php

namespace App\Nova;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;

class CustomerTickets extends Story
{
    public static function label()
    {
        return __('Customer tickets');
    }


    public static function uriKey()
    {
        return 'customer-tickets';
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            new filters\CreatorStoryFilter(),
            new filters\UserFilter(),
            new filters\StoryStatusFilter(),
            new Filters\TaggableTypeFilter(),
            new filters\StoryTypeFilter(),
            new filters\CustomerStoryWithDeadlineFilter(),
        ];
    }
    public static function indexQuery(NovaRequest $request, $query)
    {
        $customerRole = UserRole::Customer;
        return $query->whereNotNull('creator_id')
            ->whereHas('creator', function ($q) use ($customerRole) {
                $q->whereJsonContains('roles', $customerRole);
            });
    }
    public function cards(NovaRequest $request)
    {
        $query = $this->indexQuery($request,  Story::query());
        return [
            (new Metrics\DynamicPartitionMetric('Customer', $query, 'creator_id', \App\Models\User::class, 'name'))->width('full'),
            (new Metrics\DynamicPartitionMetric('Status', $query,  'status'))->width('1/2'),
            (new Metrics\DynamicPartitionMetric('Type', $query, 'type'))->width('1/2'),
        ];
    }
}
