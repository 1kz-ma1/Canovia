<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareerCapturePayload extends Model
{
    protected $fillable = [
        'career_capture_id',
        'screenshot_data',
        'byte_size',
    ];

    protected $hidden = ['screenshot_data'];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
        ];
    }

    public function capture()
    {
        return $this->belongsTo(CareerCapture::class, 'career_capture_id');
    }
}
