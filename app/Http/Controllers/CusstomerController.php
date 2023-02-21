<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCusstomerRequest;
use App\Http\Requests\UpdateCusstomerRequest;
use App\Models\Cusstomer;

class CusstomerController extends Controller
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
     * @param  \App\Http\Requests\StoreCusstomerRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCusstomerRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Cusstomer  $cusstomer
     * @return \Illuminate\Http\Response
     */
    public function show(Cusstomer $cusstomer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Cusstomer  $cusstomer
     * @return \Illuminate\Http\Response
     */
    public function edit(Cusstomer $cusstomer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCusstomerRequest  $request
     * @param  \App\Models\Cusstomer  $cusstomer
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCusstomerRequest $request, Cusstomer $cusstomer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Cusstomer  $cusstomer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Cusstomer $cusstomer)
    {
        //
    }
}
