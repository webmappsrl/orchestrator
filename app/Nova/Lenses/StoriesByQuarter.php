<?php

namespace App\Nova\Lenses;

use App\Enums\UserRole;
use App\Models\Story;
use App\Nova\Story as NovaStory;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Lenses\Lens;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;

class StoriesByQuarter extends Lens
{
    protected $quarter;

    /**
     * Create a new lens instance.
     *
     * @param  string  $quarter
     * @return void
     */
    public function __construct($quarter)
    {
        $this->quarter = $quarter;
    }

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\LensRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function query(LensRequest $request, $query)
    {
        $quarter = (int) substr($request->route('lens'), -1);
        $currentYear = date('Y');
        return $query->whereRaw('EXTRACT(QUARTER FROM created_at) = ?', [$quarter])
            ->whereYear('created_at', $currentYear)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get the fields displayed by the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\LensRequest  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make('ID', 'id')->sortable(),
            $this->infoField($request),
            Text::make('Name', 'name'),
            DateTime::make('Created At', 'created_at'),
            Text::make('Quarter', function () {
                return 'Q' . $this->quarter;
            }),
        ];
    }

    /**
     * Get the name of the lens.
     *
     * @return string
     */
    public function name()
    {
        return 'Stories Q' . $this->quarter;
    }

    /**
     * Get the URI key for the lens.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'stories-q' . $this->quarter;
    }

    public function infoField(NovaRequest $request, $fieldName = 'info')
    {
        return Text::make(__('Info'), $fieldName, function () use ($request) {
            if ($request->user()->hasRole(UserRole::Customer)) {
                return $this->getCustomerInfo();
            } else {
                return $this->getNonCustomerInfo();
            }
        })
            ->asHtml();
    }

    private function getCustomerInfo()
    {
        $statusColor = $this->getStatusColor($this->status);
        $storyType = $this->type;
        return <<<HTML
            Status: <span style="background-color:{$statusColor}; color: white; padding: 2px 4px;">{$this->status}</span> 
            <br> 
            <span style="color:blue">{$storyType}</span>
            HTML;
    }
    private function getNonCustomerInfo()
    {
        $appLink = $this->getAppLink();
        $tagLinks = $this->getTagLinks();
        $creatorLink = $this->getCreatorLink();

        return "{$appLink}{$creatorLink}{$tagLinks}";
    }

    private function getAppLink($creator = null)
    {
        if (is_null($creator)) {
            $creator = $this->resource->creator;
        }
        $app = isset($creator) && isset($creator->apps) && count($creator->apps) > 0 ? $creator->apps[0] : null;

        if ($app) {
            $url = url("/resources/apps/{$app->id}");
            return <<<HTML
            <a 
                href="{$url}" 
                target="_blank" 
                style="color:red; font-weight:bold;">
                App: {$app->name}
            </a> <br>
            HTML;
        }
        return '';
    }
    private function getTagLinks()
    {
        $tags = $this->resource->tags;
        $HTML = '';
        if ($tags) {
            foreach ($tags as $tag) {
                $url = $tag->getResourceUrlAttribute();
                $HTML .=    <<<HTML
            <a 
                href="$url"
                target="_blank" 
                style="color:orange; font-weight:bold;">
                {$tag->name}
            </a> <br>
            HTML;
            }
            return $HTML;
        }
        return '';
    }
    private function getCreatorLink()
    {
        $creator = $this->resource->creator;
        if ($creator) {
            $url = url("/resources/users/{$creator->id}");
            return <<<HTML
            <a 
                href="{$url}" 
                target="_blank" 
                style="color:chocolate; font-weight:bold;">
                Creator: {$creator->name}
            </a> <br>
            HTML;
        }
        return '';
    }
}
