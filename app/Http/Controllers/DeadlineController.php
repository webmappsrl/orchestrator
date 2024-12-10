<?php

namespace App\Http\Controllers;

use App\Enums\StoryStatus;
use App\Http\Requests\StoreDeadlineRequest;
use App\Http\Requests\UpdateDeadlineRequest;
use App\Models\Deadline;

class DeadlineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDeadlineRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Deadline $deadline)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Deadline $deadline)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDeadlineRequest $request, Deadline $deadline)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Deadline $deadline)
    {
        //
    }

    /**
     * Get the email for the deadline
     */
    public function email($id)
    {
        $deadline = Deadline::findOrFail($id);
        $stories = $deadline->stories()->get();
        $customer = $deadline->customer()->first();

        $doneStories = $deadline->stories()->where('status', StoryStatus::Done)->get();
        $progressStories = $deadline->stories()->whereIn(
            'status',
            [
                StoryStatus::Test,
                StoryStatus::Progress,
                StoryStatus::Todo
            ]
        )->get();
        $storiesToStart = $deadline->stories()->where('status', StoryStatus::New)->get();

        return view(
            'deadline-email',
            [
                'deadline' => $deadline,
                'stories' => $stories,
                'doneStories' => $doneStories,
                'progressStories' => $progressStories,
                'storiesToStart' => $storiesToStart,
                'customer' => $customer,
            ]
        );
    }
}
