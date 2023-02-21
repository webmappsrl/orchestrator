<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLayerRequest;
use App\Http\Requests\UpdateLayerRequest;
use App\Models\Layer;

class LayerController extends Controller
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
     * @param  \App\Http\Requests\StoreLayerRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreLayerRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Layer  $layer
     * @return \Illuminate\Http\Response
     */
    public function show(Layer $layer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Layer  $layer
     * @return \Illuminate\Http\Response
     */
    public function edit(Layer $layer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateLayerRequest  $request
     * @param  \App\Models\Layer  $layer
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateLayerRequest $request, Layer $layer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Layer  $layer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Layer $layer)
    {
        //
    }
}
