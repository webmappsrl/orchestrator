<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Nova\App;
use App\Nova\ArchivedDeadlines;
use App\Nova\ArchivedQuotes;
use App\Nova\ArchivedStories;
use App\Nova\ArchivedStoryShowedByCustomer;
use App\Nova\AssignedToMeStory;
use App\Nova\BacklogStory;
use App\Nova\Customer;
use App\Nova\CustomerStory;
use App\Nova\CustomerTickets;
use App\Nova\Dashboards\Kanban;
use App\Nova\Dashboards\Marketing;
use App\Nova\Documentation;
use App\Nova\Layer;
use App\Nova\Product;
use App\Nova\Project;
use App\Nova\Quote;
use App\Nova\RecurringProduct;
use App\Nova\Renewals;
use App\Nova\StoryShowedByCustomer;
use App\Nova\Tag;
use App\Nova\ToBeTestedStory;
use App\Nova\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Menu\MenuGroup;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        Nova::withBreadcrumbs(true);

        Nova::style('nova-custom', public_path('/nova-custom.css'));

        Nova::script('renewals-italian-format', public_path('js/renewals-format.js'));

        Nova::mainMenu(function (Request $request) {
            $newStoryUrl = '/resources/stories/new';
            if (auth()->user()->hasRole(UserRole::Customer)) {
                $newStoryUrl = '/resources/story-showed-by-customers/new';
            }

            $scrumMeetCode = config('app.SCRUM_MEET_CODE');

            return [
                MenuItem::externalLink('SCRUM', route('scrum.meeting', ['meetCode' => $scrumMeetCode]))->openInNewTab()->canSee(function ($request) {
                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                }),
                MenuItem::externalLink('MEET', 'https://meet.google.com/'.$scrumMeetCode)->openInNewTab()->canSee(function ($request) {
                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                }),
                MenuSection::dashboard(Kanban::class)->icon('chart-bar')->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }

                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                }),
                MenuSection::make('ADMIN', [
                    MenuItem::resource(User::class),
                    MenuGroup::make(__('Reports'), [
                        MenuItem::externalLink('all times', url('/report'))->openInNewTab()->canSee(function ($request) {
                            return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Developer);
                        }),
                        ...collect(range(now()->year, 2023))->map(function ($year) {
                            return MenuItem::externalLink((string) $year, url("/report/{$year}"))->openInNewTab()
                                ->canSee(function ($request) {
                                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Developer);
                                });
                        }),
                    ])->collapsedByDefault(),

                ])->icon('user')->collapsedByDefault()->collapsedByDefault(),

                MenuSection::make('APP', [
                    MenuItem::resource(App::class),
                    MenuItem::resource(Layer::class),
                ])->icon('document-text')->collapsedByDefault()->collapsedByDefault(),

                MenuSection::make('CRM', [
                    MenuGroup::make(__('Archived'), [
                        MenuItem::resource(ArchivedQuotes::class),
                    ])->collapsedByDefault(),
                    MenuItem::link(__('Marketing'), '/dashboards/marketing'),
                    MenuItem::resource(Customer::class),
                    MenuItem::resource(Renewals::class)->canSee(function ($request) {
                        if ($request->user() == null) {
                            return false;
                        }

                        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager);
                    }),
                    MenuItem::resource(CustomerTickets::class)->canSee(function ($request) {
                        if ($request->user() == null) {
                            return false;
                        }

                        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                    }),
                    MenuItem::resource(Project::class),
                    MenuItem::resource(Product::class),
                    MenuItem::resource(RecurringProduct::class),
                    MenuItem::resource(Quote::class),
                ])->icon('users')->collapsedByDefault()->canSee(function ($request) {
                    if ($request->user() === null) {
                        return false;
                    }

                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager);
                }),

                MenuSection::make('DEV', [
                    MenuGroup::make(__('Archived'), [
                        MenuItem::resource(ArchivedDeadlines::class),
                        MenuItem::resource(ArchivedStories::class),

                    ])->collapsedByDefault(),
                    MenuGroup::make(__('my work'), [
                        MenuItem::resource(AssignedToMeStory::class),
                        MenuItem::resource(ToBeTestedStory::class),
                    ])->collapsedByDefault(),
                    MenuItem::resource(Tag::class),
                    MenuItem::resource(Documentation::class),
                    MenuItem::resource(BacklogStory::class),
                    MenuItem::resource(CustomerStory::class),
                ])->icon('code')->collapsable()->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }

                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                }),

                MenuSection::make(__('CUSTOMER'), [
                    MenuItem::resource(Documentation::class),
                    MenuItem::resource(ArchivedStoryShowedByCustomer::class),
                    MenuItem::resource(StoryShowedByCustomer::class),
                ])->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }

                    return $request->user()->hasRole(UserRole::Customer);
                })->icon('at-symbol')->collapsable(),

                MenuSection::make(__('ACTIONS'), [
                    MenuItem::link('Create a new story', $newStoryUrl),
                    MenuItem::externalLink('Horizon', url('/horizon'))->openInNewTab()->canSee(function ($request) {
                        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Developer);
                    }),
                    MenuItem::externalLink('logs', url('logs'))->openInNewTab()->canSee(function ($request) {
                        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Developer);
                    }),
                    MenuItem::externalLink('Google Calendar', 'https://calendar.google.com/calendar/u/0/r')->openInNewTab()->canSee(function ($request) {
                        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                    }),
                ])->icon('pencil')->collapsedByDefault(),

            ];
        });

        $this->getFooter();
    }

    /**
     * Register the Nova routes.
     *
     * @return void
     */
    protected function routes()
    {
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withPasswordResetRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewNova', function ($user) {
            $userIsAdmin = $user->hasRole(UserRole::Admin);
            $userIsEditor = $user->hasRole(UserRole::Editor);
            $userIsDeveloper = $user->hasRole(UserRole::Developer);
            $userIsManager = $user->hasRole(UserRole::Manager);
            $userIsCustomer = $user->hasRole(UserRole::Customer);
            $debug = config('services.app_environment');

            if (config('services.app_environment') == 'production' || config('services.app_environment') == 'develop') {
                return $userIsAdmin || $userIsEditor || $userIsDeveloper || $userIsManager || $userIsCustomer;
            }

            return true;
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array
     */
    protected function dashboards()
    {
        return [
            new \App\Nova\Dashboards\Kanban,
            new \App\Nova\Dashboards\Marketing,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array
     */
    public function tools()
    {
        return [
            new \Badinansoft\LanguageSwitch\LanguageSwitch(),
        ];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Nova::initialPath(function (Request $request) {
            if (! $request->user() == null) {
                $user = $request->user();
                if ($user->hasRole(UserRole::Customer)) {
                    return $user->initialPath();
                }

                // Per tutti gli altri utenti (Admin, Manager, Developer),
                // reindirizza alla dashboard Kanban
                if ($user->hasRole(UserRole::Admin) ||
                    $user->hasRole(UserRole::Manager) ||
                    $user->hasRole(UserRole::Developer)
                ) {
                    return '/dashboards/kanban';
                }
            }
        });
    }

    //create a footer
    private function getFooter()
    {
        Nova::footer(function () {
            return Blade::render('nova/footer');
        });
    }
}
