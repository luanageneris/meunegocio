<?php

namespace App\Http\Controllers\Api;

use App\Models\WorkShift;
use App\Http\Requests\StoreWorkShiftRequest;
use App\Http\Requests\UpdateWorkShiftRequest;
use Illuminate\Http\Request;

class WorkShiftController
{
    /**
     * List work shifts for current company
     */
    public function index(Request $request)
    {
        $shifts = WorkShift::forCompany()
            ->with('collaborator')
            ->orderBy('date', 'desc')
            ->paginate(30);

        return response()->json($shifts);
    }

    /**
     * Store a new work shift
     */
    public function store(StoreWorkShiftRequest $request)
    {
        $shift = WorkShift::create($request->validated());
        
        return response()->json($shift->load('collaborator'), 201);
    }

    /**
     * Show a work shift
     */
    public function show(Request $request, WorkShift $workShift)
    {
        if ($workShift->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($workShift->load('collaborator'));
    }

    /**
     * Update a work shift
     */
    public function update(UpdateWorkShiftRequest $request, WorkShift $workShift)
    {
        if ($workShift->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $workShift->update($request->validated());

        return response()->json($workShift->load('collaborator'));
    }

    /**
     * Delete a work shift (soft delete)
     */
    public function destroy(Request $request, WorkShift $workShift)
    {
        if ($workShift->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $workShift->delete();

        return response()->json(['message' => 'Turno removido com sucesso']);
    }

    /**
     * Get shifts for a specific date
     */
    public function byDate(Request $request, $date)
    {
        $shifts = WorkShift::forCompany()
            ->whereDate('date', $date)
            ->with('collaborator')
            ->orderBy('start_time')
            ->get();

        return response()->json($shifts);
    }

    /**
     * Get shifts for a collaborator between dates
     */
    public function collaboratorShifts(Request $request, $collaboratorId)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $shifts = WorkShift::forCompany()
            ->where('collaborator_id', $collaboratorId)
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->with('collaborator')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json($shifts);
    }

    /**
     * Get monthly schedule (all collaborators)
     */
    public function monthlySchedule(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2000',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year = $request->year;
        $month = $request->month;

        $shifts = WorkShift::forCompany()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->with('collaborator')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Group by date
        $grouped = $shifts->groupBy(function($shift) {
            return $shift->date->format('Y-m-d');
        });

        return response()->json($grouped->toArray());
    }
}
