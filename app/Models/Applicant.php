<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'student_id',
        'phone',
        'address',
        'course',
        'year_level',
        'scholarship_id',
        'date_applied',
        'status'
    ];

    // Relationship to Scholarship
    public function scholarship()
    {
        return $this->belongsTo(Scholarship::class);
    }
}