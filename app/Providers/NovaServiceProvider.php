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
use App\Nova\InProgressStory;
use App\Nova\Customer;
use App\Nova\CustomerFundraisingOpportunity;
use App\Nova\CustomerFundraisingProject;
use App\Nova\CustomerStory;
use App\Nova\CustomerTickets;
use App\Nova\Dashboards\Kanban2;
use App\Nova\Dashboards\Activity;
use App\Nova\Dashboards\ActivityUser;
use App\Nova\Dashboards\ActivityTags;
use App\Nova\Dashboards\ActivityCustomer;
use App\Nova\Dashboards\ActivityOrganizations;
use App\Nova\Dashboards\ActivityTagsDetails;
use App\Nova\Dashboards\ActivityCustomerDetails;
use App\Nova\Dashboards\CustomerDashboard;
use App\Nova\Dashboards\TicketStatus;
use App\Nova\Dashboards\Changelog;
use App\Nova\Documentation;
use App\Nova\FundraisingOpportunity;
use App\Nova\FundraisingProject;
use App\Nova\Layer;
use App\Nova\Product;
use App\Nova\Project;
use App\Nova\Quote;
use App\Nova\RecurringProduct;
use App\Nova\StoryShowedByCustomer;
use App\Nova\Tag;
use App\Nova\ToBeTestedStory;
use App\Nova\User;
use App\Nova\Organization;
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

        Nova::mainMenu(function (Request $request) {
            $newStoryUrl = '/resources/stories/new';
            if (auth()->user()->hasRole(UserRole::Customer)) {
                $newStoryUrl = '/resources/story-showed-by-customers/new';
            }

            $scrumMeetCode = config('app.SCRUM_MEET_CODE');

            return [
                MenuSection::make('HELP', [
                    MenuItem::resource(Documentation::class),
                    MenuItem::dashboard(TicketStatus::class),
                    MenuItem::dashboard(Changelog::class),
                ])->icon('question-mark-circle')->collapsable()->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }

                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                }),

                MenuSection::make('AGILE', [
                    MenuItem::dashboard(Kanban2::class),
                    MenuItem::externalLink('SCRUM', route('scrum.meeting', ['meetCode' => $scrumMeetCode]))->openInNewTab()->canSee(function ($request) {
                        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                    }),
                    MenuItem::externalLink('MEET', 'https://meet.google.com/'.$scrumMeetCode)->openInNewTab()->canSee(function ($request) {
                        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                    }),
                    MenuItem::dashboard(Activity::class),
                    MenuGroup::make(__('Tickets'), [
                        MenuItem::link(__('Nuovi'), '/resources/new-stories'),
                        MenuItem::link(__('Customers'), '/resources/customer-stories'),
                        MenuItem::link(__('In progress'), '/resources/in-progress-stories'),
                        MenuItem::link(__('Da svolgere'), '/resources/assigned-to-me-stories'),
                        MenuItem::link(__('Test'), '/resources/to-be-tested-stories'),
                        MenuItem::link(__('Backlog'), '/resources/backlog-stories'),
                        MenuItem::link(__('Archiviati'), '/resources/archived-stories'),
                    ])->collapsedByDefault(),
                ])->icon('code')->collapsable()->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }

                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager) || $request->user()->hasRole(UserRole::Developer);
                }),

                MenuSection::make('FUNDRAISING', [
                    MenuItem::resource(FundraisingOpportunity::class),
                    MenuItem::resource(FundraisingProject::class),
                ])->icon('currency-dollar')->collapsable()->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }

                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Fundraising);
                }),

                MenuSection::make('NEW', [
                    MenuItem::link(__('Ticket'), '/resources/customer-stories/new'),
                    MenuItem::link(__('FundRaising'), '/resources/fundraising-opportunities/new')->canSee(function ($request) {
                        if ($request->user() == null) {
                            return false;
                        }
                        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Fundraising);
                    }),
                    MenuItem::link(__('Tag'), '/resources/tags/new')->canSee(function ($request) {
                        if ($request->user() == null) {
                            return false;
                        }
                        return $request->user()->hasRole(UserRole::Admin);
                    }),
                    MenuItem::link(__('User'), '/resources/users/new')->canSee(function ($request) {
                        if ($request->user() == null) {
                            return false;
                        }
                        return $request->user()->hasRole(UserRole::Admin);
                    }),
                    MenuItem::link(__('Organization'), '/resources/organizations/new')->canSee(function ($request) {
                        if ($request->user() == null) {
                            return false;
                        }
                        return $request->user()->hasRole(UserRole::Admin);
                    }),
                ])->icon('plus-circle')->collapsable()->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return !$request->user()->hasRole(UserRole::Customer);
                }),

                MenuSection::make('CRM', [
                    MenuGroup::make(__('Archived'), [
                        MenuItem::resource(ArchivedQuotes::class),
                    ])->collapsedByDefault(),
                    MenuItem::resource(Customer::class),
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
                    if ($request->user() == null) {
                        return false;
                    }
                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager);
                }),

                MenuSection::make(__('CUSTOMER'), [
                    MenuItem::dashboard(CustomerDashboard::class),
                    MenuItem::resource(Documentation::class),
                    MenuItem::resource(ArchivedStoryShowedByCustomer::class),
                    MenuItem::resource(StoryShowedByCustomer::class),
                    MenuGroup::make(__('FundRaising'), [
                        MenuItem::resource(CustomerFundraisingOpportunity::class),
                        MenuItem::resource(CustomerFundraisingProject::class),
                    ])->collapsedByDefault(),
                ])->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }

                    return $request->user()->hasRole(UserRole::Customer);
                })->icon('at-symbol')->collapsable(),

                MenuSection::make('MANAGEMENT', [
                    MenuItem::dashboard(ActivityUser::class),
                    MenuGroup::make(__('Statistics'), [
                        MenuItem::dashboard(ActivityTags::class),
                        MenuItem::dashboard(ActivityCustomer::class),
                        MenuItem::dashboard(ActivityOrganizations::class),
                    ])->collapsedByDefault(),
                    MenuGroup::make(__('Details'), [
                        MenuItem::dashboard(ActivityTagsDetails::class),
                        MenuItem::dashboard(ActivityCustomerDetails::class),
                    ])->collapsedByDefault(),
                ])->icon('cog')->collapsable()->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager);
                }),

                MenuSection::make('ADMIN', [
                    MenuItem::resource(Tag::class),
                    MenuItem::resource(Organization::class),
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
                ])->icon('user')->collapsedByDefault()->collapsedByDefault(),

                MenuSection::make('APP', [
                    MenuItem::resource(App::class),
                    MenuItem::resource(Layer::class),
                ])->icon('document-text')->collapsedByDefault()->collapsedByDefault(),

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
            $userIsFundraising = $user->hasRole(UserRole::Fundraising);
            $debug = config('services.app_environment');

            if (config('services.app_environment') == 'production' || config('services.app_environment') == 'develop') {
                return $userIsAdmin || $userIsEditor || $userIsDeveloper || $userIsManager || $userIsCustomer || $userIsFundraising;
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
            new \App\Nova\Dashboards\Kanban2,
            new \App\Nova\Dashboards\Activity,
            new \App\Nova\Dashboards\ActivityUser,
            new \App\Nova\Dashboards\ActivityTags,
            new \App\Nova\Dashboards\ActivityCustomer,
            new \App\Nova\Dashboards\ActivityOrganizations,
            new \App\Nova\Dashboards\ActivityTagsDetails,
            new \App\Nova\Dashboards\ActivityCustomerDetails,
            new \App\Nova\Dashboards\CustomerDashboard,
            new \App\Nova\Dashboards\TicketStatus,
            new \App\Nova\Dashboards\Changelog,
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


                // Per tutti gli altri utenti (Admin, Manager, Developer, Fundraising),
                if ($user->hasRole(UserRole::Admin) ||
                $user->hasRole(UserRole::Manager) ||
                $user->hasRole(UserRole::Fundraising) ||
                $user->hasRole(UserRole::Developer)) {
                    return 'resources/customer-stories';
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
