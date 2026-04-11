<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicantController extends Controller
{
    // ==================== PUBLIC / STUDENT FUNCTIONS ====================
    
    /**
     * Get all applicants (Admin only - shows all)
     * GET /api/applicants
     */
    public function index()
    {
        // Check if user is admin
        if (Auth::user()->role_id == 1) {
            $applicants = Applicant::with('scholarship')->get();
        } else {
            // Students can only see their own applicants
            $applicants = Applicant::with('scholarship')
                ->where('email', Auth::user()->email)
                ->orWhere('student_id', Auth::user()->student_id)
                ->get();
        }
        
        return response()->json([
            'success' => true,
            'data' => $applicants
        ], 200);
    }

    /**
     * Store a new applicant (Student only)
     * POST /api/applicants
     */
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
        ]);

        // Always set status to pending for new applications
        $validated['status'] = 'pending';
        
        $applicant = Applicant::create($validated);
        $applicant->load('scholarship');

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully! Awaiting admin approval.',
            'data' => $applicant
        ], 201);
    }

    /**
     * Get single applicant (with permission check)
     * GET /api/applicants/{id}
     */
    public function show($id)
    {
        $applicant = Applicant::with('scholarship')->find($id);
        
        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }
        
        // Check permission: Admin can view any, Student can only view their own
        if (Auth::user()->role_id != 1) {
            if ($applicant->email != Auth::user()->email && 
                $applicant->student_id != Auth::user()->student_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this application'
                ], 403);
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $applicant
        ], 200);
    }

    /**
     * Update applicant (with permission check)
     * PUT /api/applicants/{id}
     */
    public function update(Request $request, $id)
    {
        $applicant = Applicant::find($id);
        
        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }
        
        // Check permission
        if (Auth::user()->role_id != 1) {
            // Student can only edit their own pending applications
            if ($applicant->email != Auth::user()->email && 
                $applicant->student_id != Auth::user()->student_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to edit this application'
                ], 403);
            }
            
            // Student cannot edit approved or rejected applications
            if ($applicant->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit. This application has already been ' . $applicant->status
                ], 400);
            }
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
        ]);

        // Only admin can change status
        if (Auth::user()->role_id == 1 && $request->has('status')) {
            $validated['status'] = $request->status;
        }

        $applicant->update($validated);
        $applicant->load('scholarship');

        return response()->json([
            'success' => true,
            'message' => 'Applicant updated successfully',
            'data' => $applicant
        ], 200);
    }

    /**
     * Delete applicant (with permission check)
     * DELETE /api/applicants/{id}
     */
    public function destroy($id)
    {
        $applicant = Applicant::find($id);
        
        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }
        
        // Check permission
        if (Auth::user()->role_id != 1) {
            // Student can only delete their own pending applications
            if ($applicant->email != Auth::user()->email && 
                $applicant->student_id != Auth::user()->student_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this application'
                ], 403);
            }
            
            // Student cannot delete approved or rejected applications
            if ($applicant->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete ' . $applicant->status . ' application. Please contact admin.'
                ], 400);
            }
        }
        
        $applicant->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Applicant deleted successfully'
        ], 200);
    }
    
    // ==================== ADMIN ONLY FUNCTIONS ====================
    
    /**
     * Admin: Approve applicant
     * PUT /api/admin/applicants/{id}/approve
     */
    public function approve($id, Request $request)
    {
        // Check if user is admin
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }
        
        $applicant = Applicant::find($id);
        
        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }
        
        // ========== NEW VALIDATION: Cannot approve if already rejected ==========
        if ($applicant->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot approve a rejected application. The applicant has already been rejected.',
                'data' => [
                    'current_status' => $applicant->status,
                    'rejected_at' => $applicant->rejected_at,
                    'rejection_reason' => $applicant->rejection_reason
                ]
            ], 400);
        }
        
        // ========== NEW VALIDATION: Cannot approve if already approved ==========
        if ($applicant->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Applicant is already approved',
                'data' => [
                    'current_status' => $applicant->status,
                    'approved_at' => $applicant->approved_at,
                    'approved_by' => $applicant->approver ? $applicant->approver->name : 'Unknown'
                ]
            ], 400);
        }
        
        $applicant->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'remarks' => $request->remarks ?? $applicant->remarks,
            // Clear any rejection data if it exists (in case of re-approval)
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null
        ]);
        
        $applicant->load('scholarship');
        
        return response()->json([
            'success' => true,
            'message' => 'Applicant approved successfully',
            'data' => $applicant
        ], 200);
    }
    
    /**
     * Admin: Reject applicant
     * PUT /api/admin/applicants/{id}/reject
     */
    public function reject($id, Request $request)
    {
        // Check if user is admin
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }
        
        $request->validate([
            'reason' => 'required|string|min:5'
        ]);
        
        $applicant = Applicant::find($id);
        
        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }
        
        // ========== NEW VALIDATION: Cannot reject if already approved ==========
        if ($applicant->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reject an approved application. The applicant has already been approved.',
                'data' => [
                    'current_status' => $applicant->status,
                    'approved_at' => $applicant->approved_at,
                    'approved_by' => $applicant->approver ? $applicant->approver->name : 'Unknown'
                ]
            ], 400);
        }
        
        // ========== NEW VALIDATION: Cannot reject if already rejected ==========
        if ($applicant->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Applicant is already rejected',
                'data' => [
                    'current_status' => $applicant->status,
                    'rejected_at' => $applicant->rejected_at,
                    'rejection_reason' => $applicant->rejection_reason
                ]
            ], 400);
        }
        
        $applicant->update([
            'status' => 'rejected',
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'rejection_reason' => $request->reason,
            'remarks' => $request->remarks ?? $applicant->remarks,
            // Clear any approval data if it exists
            'approved_by' => null,
            'approved_at' => null
        ]);
        
        $applicant->load('scholarship');
        
        return response()->json([
            'success' => true,
            'message' => 'Applicant rejected successfully',
            'data' => $applicant
        ], 200);
    }
    
    /**
     * Admin: Get all applicants with filters
     * GET /api/admin/applicants
     */
    public function adminIndex(Request $request)
    {
        // Check if user is admin
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }
        
        $query = Applicant::with('scholarship');
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by scholarship
        if ($request->has('scholarship_id')) {
            $query->where('scholarship_id', $request->scholarship_id);
        }
        
        $applicants = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $applicants
        ], 200);
    }
    
    /**
     * Admin: Get statistics
     * GET /api/admin/statistics
     */
    public function statistics()
    {
        // Check if user is admin
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }
        
        $total = Applicant::count();
        $pending = Applicant::where('status', 'pending')->count();
        $approved = Applicant::where('status', 'approved')->count();
        $rejected = Applicant::where('status', 'rejected')->count();
        
        $approvalRate = $total > 0 ? round(($approved / $total) * 100, 2) : 0;
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'approval_rate' => $approvalRate . '%'
            ]
        ], 200);
    }
    
    /**
     * Admin: Bulk approve applicants
     * POST /api/admin/applicants/bulk-approve
     */
    public function bulkApprove(Request $request)
    {
        // Check if user is admin
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }
        
        $request->validate([
            'applicant_ids' => 'required|array',
            'applicant_ids.*' => 'exists:applicants,id'
        ]);
        
        $approved = [];
        $failed = [];
        
        foreach ($request->applicant_ids as $id) {
            $applicant = Applicant::find($id);
            
            // Skip if already approved or rejected
            if ($applicant && $applicant->status === 'pending') {
                $applicant->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now()
                ]);
                $approved[] = $id;
            } else {
                $failed[] = [
                    'id' => $id,
                    'reason' => $applicant ? 'Already ' . $applicant->status : 'Not found'
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => count($approved) . ' applicants approved successfully',
            'data' => [
                'approved_count' => count($approved),
                'failed_count' => count($failed),
                'approved_ids' => $approved,
                'failed' => $failed
            ]
        ], 200);
    }
    
    /**
     * Admin: Bulk reject applicants
     * POST /api/admin/applicants/bulk-reject
     */
    public function bulkReject(Request $request)
    {
        // Check if user is admin
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }
        
        $request->validate([
            'applicant_ids' => 'required|array',
            'applicant_ids.*' => 'exists:applicants,id',
            'reason' => 'required|string'
        ]);
        
        $rejected = [];
        $failed = [];
        
        foreach ($request->applicant_ids as $id) {
            $applicant = Applicant::find($id);
            
            // Skip if already approved or rejected
            if ($applicant && $applicant->status === 'pending') {
                $applicant->update([
                    'status' => 'rejected',
                    'rejected_by' => Auth::id(),
                    'rejected_at' => now(),
                    'rejection_reason' => $request->reason
                ]);
                $rejected[] = $id;
            } else {
                $failed[] = [
                    'id' => $id,
                    'reason' => $applicant ? 'Already ' . $applicant->status : 'Not found'
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => count($rejected) . ' applicants rejected successfully',
            'data' => [
                'rejected_count' => count($rejected),
                'failed_count' => count($failed),
                'rejected_ids' => $rejected,
                'failed' => $failed
            ]
        ], 200);
    }
}