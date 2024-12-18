<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTask extends Model
{
    use HasFactory;

    public static $statuses = [
        'pending',
        'started',
        'completed',
    ];

    protected $fillable = [
        'user_id',
        'task_id',
        'status',
        'started_at',
        'completed_at',
        'completion_date',
        'watched_to_completion', // New field
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}