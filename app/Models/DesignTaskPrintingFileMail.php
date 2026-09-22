<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignTaskPrintingFileMail extends Model
{
    protected $fillable = [
        'design_task_id',
        'sender_id',
        'to_recipients',
        'cc_recipients',
        'subject',
        'body',
        'transfer_url',
        'transfer_urls',
        'attachments',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'to_recipients' => 'array',
            'cc_recipients' => 'array',
            'transfer_urls' => 'array',
            'attachments' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function designTask()
    {
        return $this->belongsTo(DesignTask::class, 'design_task_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
