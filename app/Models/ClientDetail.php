<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientDetail extends Model
{
    protected $fillable = [
        'client_name',
        'client_name_normalized',
        'contact_person_name',
        'mobile_number',
    ];

    /**
     * Upsert by case/whitespace-insensitive name match so repeat task creation
     * for the same client updates contact details instead of duplicating rows.
     */
    public static function rememberFromTask(string $clientName, ?string $contactPerson, ?string $mobileNumber): void
    {
        $clientName = trim($clientName);

        if ($clientName === '') {
            return;
        }

        self::updateOrCreate(
            ['client_name_normalized' => mb_strtolower($clientName)],
            [
                'client_name' => $clientName,
                'contact_person_name' => $contactPerson !== null && trim($contactPerson) !== '' ? trim($contactPerson) : null,
                'mobile_number' => $mobileNumber !== null && trim($mobileNumber) !== '' ? trim($mobileNumber) : null,
            ]
        );
    }
}
