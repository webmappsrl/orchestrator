<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEpicRequest;
use App\Http\Requests\UpdateEpicRequest;
use App\Models\Epic;

class EpicController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreEpicRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreEpicRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Epic  $epic
     * @return \Illuminate\Http\Response
     */
    public function show(Epic $epic)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Epic  $epic
     * @return \Illuminate\Http\Response
     */
    public function edit(Epic $epic)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateEpicRequest  $request
     * @param  \App\Models\Epic  $epic
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateEpicRequest $request, Epic $epic)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Epic  $epic
     * @return \Illuminate\Http\Response
     */
    public function destroy(Epic $epic)
    {
        //
    }
}
