<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Scholarship;
use App\Models\Applicant;

class ScholarshipController extends Controller
{
    public function index()
    {
        // Get all scholarships without the accessor
        $scholarships = Scholarship::all();
        
        return response()->json([
            'success' => true,
            'data' => $scholarships
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'required|string',
            'provider'    => 'required|string|max:255',
            'slots'       => 'required|integer|min:1',
            'amount'      => 'required|numeric',
            'deadline'    => 'required|date',
            'status'      => 'required|string|in:Active,Inactive,Closed',
        ]);

        $scholarship = Scholarship::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Scholarship created successfully!',
            'data'    => $scholarship
        ], 201);
    }

    public function show(string $id)
    {
        $scholarship = Scholarship::find($id);

        if (!$scholarship) {
            return response()->json(['message' => 'Scholarship not found'], 404);
        }

        // Calculate applicant count and remaining slots
        $applicantCount = Applicant::where('scholarship_id', $id)->count();
        $remainingSlots = $scholarship->slots - $applicantCount;

        return response()->json([
            'success' => true,
            'data' => $scholarship,
            'total_applicants' => $applicantCount,
            'remaining_slots' => $remainingSlots
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $scholarship = Scholarship::find($id);

        if (!$scholarship) {
            return response()->json(['message' => 'Scholarship not found'], 404);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'provider'    => 'sometimes|string|max:255',
            'slots'       => 'sometimes|integer|min:1',
            'amount'      => 'sometimes|numeric',
            'deadline'    => 'sometimes|date',
            'status'      => 'sometimes|string|in:Active,Inactive,Closed',
        ]);

        $scholarship->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Scholarship updated successfully!',
            'data'    => $scholarship
        ], 200);
    }

    public function destroy(string $id)
    {
        $scholarship = Scholarship::find($id);

        if (!$scholarship) {
            return response()->json(['message' => 'Scholarship not found'], 404);
        }

        $scholarship->delete();

        return response()->json([
            'success' => true,
            'message' => 'Scholarship deleted successfully'
        ], 200);
    }

    /**
     * Get all applicants for a specific scholarship
     * GET /api/scholarships/{id}/applicants
     */
    public function getApplicants($id)
    {
        $scholarship = Scholarship::find($id);
        
        if (!$scholarship) {
            return response()->json(['message' => 'Scholarship not found'], 404);
        }
        
        $applicants = Applicant::where('scholarship_id', $id)->get();
        
        return response()->json([
            'success' => true,
            'scholarship' => $scholarship->name,
            'total_applicants' => $applicants->count(),
            'data' => $applicants
        ], 200);
    }

    /**
     * Get applicants by status (pending, approved, rejected)
     * GET /api/scholarships/{id}/applicants/{status}
     */
    public function getApplicantsByStatus($id, $status)
    {
        $scholarship = Scholarship::find($id);
        
        if (!$scholarship) {
            return response()->json(['message' => 'Scholarship not found'], 404);
        }
        
        $applicants = Applicant::where('scholarship_id', $id)
            ->where('status', $status)
            ->get();
        
        return response()->json([
            'success' => true,
            'scholarship' => $scholarship->name,
            'status' => $status,
            'count' => $applicants->count(),
            'data' => $applicants
        ], 200);
    }

    /**
     * Update applicant status
     * PUT /api/scholarships/{id}/applicants/{applicantId}/status
     */
    public function updateApplicantStatus(Request $request, $id, $applicantId)
    {
        $scholarship = Scholarship::find($id);
        
        if (!$scholarship) {
            return response()->json(['message' => 'Scholarship not found'], 404);
        }
        
        $applicant = Applicant::where('id', $applicantId)
            ->where('scholarship_id', $id)
            ->first();
        
        if (!$applicant) {
            return response()->json(['message' => 'Applicant not found for this scholarship'], 404);
        }
        
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected'
        ]);
        
        $applicant->update([
            'status' => $validated['status']
        ]);
        
        return response()->json([
            'success' => true,
            'message' => "Application status updated to {$validated['status']}",
            'data' => [
                'applicant_id' => $applicant->id,
                'name' => $applicant->first_name . ' ' . $applicant->last_name,
                'scholarship' => $scholarship->name,
                'status' => $validated['status']
            ]
        ], 200);
    }

    /**
     * Get available scholarships (active only)
     * GET /api/scholarships/available
     */
    public function available()
    {
        $scholarships = Scholarship::where('status', 'Active')->get();
        
        return response()->json([
            'success' => true,
            'data' => $scholarships
        ], 200);
    }

    /**
     * Get dashboard statistics
     * GET /api/scholarships/statistics
     */
    public function statistics()
    {
        $totalScholarships = Scholarship::count();
        $activeScholarships = Scholarship::where('status', 'Active')->count();
        $inactiveScholarships = Scholarship::where('status', 'Inactive')->count();
        $closedScholarships = Scholarship::where('status', 'Closed')->count();
        
        $totalApplicants = Applicant::count();
        $totalApproved = Applicant::where('status', 'approved')->count();
        $totalPending = Applicant::where('status', 'pending')->count();
        $totalRejected = Applicant::where('status', 'rejected')->count();
        
        // Get scholarship with most applicants
        $topScholarship = Scholarship::withCount('applicants')
            ->orderBy('applicants_count', 'desc')
            ->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'scholarships' => [
                    'total' => $totalScholarships,
                    'active' => $activeScholarships,
                    'inactive' => $inactiveScholarships,
                    'closed' => $closedScholarships
                ],
                'applicants' => [
                    'total' => $totalApplicants,
                    'approved' => $totalApproved,
                    'pending' => $totalPending,
                    'rejected' => $totalRejected
                ],
                'top_scholarship' => $topScholarship ? [
                    'name' => $topScholarship->name,
                    'applicants_count' => $topScholarship->applicants_count
                ] : null
            ]
        ], 200);
    }
}