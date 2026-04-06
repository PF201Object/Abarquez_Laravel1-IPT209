<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use Illuminate\Http\Request;

class ApplicantController extends Controller
{
    public function index()
    {
        $applicants = Applicant::with('scholarship')->get();
        
        return response()->json([
            'success' => true,
            'data' => $applicants
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:applicants',
            'student_id' => 'required|string|unique:applicants',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'course' => 'required|string|max:255',
            'year_level' => 'required|string|max:50',
            'scholarship_id' => 'required|exists:scholarships,id',
            'date_applied' => 'required|date',
            'status' => 'nullable|in:pending,approved,rejected'
        ]);

        $applicant = Applicant::create($validated);
        $applicant->load('scholarship');

        return response()->json([
            'success' => true,
            'message' => 'Applicant created successfully',
            'data' => $applicant
        ], 201);
    }

    public function show($id)
    {
        $applicant = Applicant::with('scholarship')->find($id);
        
        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $applicant
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $applicant = Applicant::find($id);
        
        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:applicants,email,' . $id,
            'student_id' => 'sometimes|string|unique:applicants,student_id,' . $id,
            'phone' => 'sometimes|string|max:20',
            'address' => 'nullable|string',
            'course' => 'sometimes|string|max:255',
            'year_level' => 'sometimes|string|max:50',
            'scholarship_id' => 'sometimes|exists:scholarships,id',
            'date_applied' => 'sometimes|date',
            'status' => 'sometimes|in:pending,approved,rejected'
        ]);

        $applicant->update($validated);
        $applicant->load('scholarship');

        return response()->json([
            'success' => true,
            'message' => 'Applicant updated successfully',
            'data' => $applicant
        ], 200);
    }

    public function destroy($id)
    {
        $applicant = Applicant::find($id);
        
        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }
        
        $applicant->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Applicant deleted successfully'
        ], 200);
    }
}