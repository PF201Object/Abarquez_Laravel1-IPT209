<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scholarship extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'provider',
        'slots',
        'amount',
        'deadline',
        'status'
    ];

    // Add this relationship
    public function applicants()
    {
        return $this->hasMany(Applicant::class);
    }
}