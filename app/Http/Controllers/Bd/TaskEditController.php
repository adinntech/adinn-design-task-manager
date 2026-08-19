<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskEditHistory;
use App\Services\DesignTaskProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskEditController extends Controller
{
    private const LOCKED_EDIT_STATUSES = [
        'waiting_confirmation',
        'rework',
        'completed',
    ];

    private const EDITABLE_CORE_FIELDS = [
        'priority' => 'Priority',
        'due_at' => 'Due Date',
        'total_creatives' => 'Total Creatives',
    ];

    /**
     * Requirement field definitions mirror the Create Task form.
     * Every field for the task's current Vertical + Task Nature is rendered,
     * whether it already has data or is still empty.
     */
    private const REQUIREMENT_FIELDS = [
        'outdoor' => [
            'mockup_requirements' => [
                [
                    'outdoor_type',
                    'Outdoor Type',
                    'select',
                    ['Bus Shelter', 'Unipole', 'Standard', 'Auto Branding', 'Pole Kiosk', 'Digital', 'Signal Post'],
                ],
                [
                    'board_type',
                    'Display Type',
                    'select',
                    ['Static', 'Digital'],
                ],
                [
                    'board_details',
                    'Board / Display Size Details',
                    'dimensions',
                    [],
                ],
                [
                    'mockup_type',
                    'Mockup Type',
                    'select',
                    ['Mock-up', 'Innovative Mock-up'],
                ],
                [
                    'site_photo',
                    'Site Photo',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'website_link',
                    'Website Link',
                    'url',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'creative_adaptation' => [
                [
                    'outdoor_type',
                    'Outdoor Type',
                    'select',
                    ['Bus Shelter', 'Unipole', 'Standard', 'Auto Branding', 'Pole Kiosk', 'Digital', 'Signal Post'],
                ],
                [
                    'board_type',
                    'Display Type',
                    'select',
                    ['Static', 'Digital'],
                ],
                [
                    'board_details',
                    'Board / Display Size Details',
                    'dimensions',
                    [],
                ],
                [
                    'site_photo',
                    'Site Photo',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'new_creative_design' => [
                [
                    'outdoor_type',
                    'Outdoor Type',
                    'select',
                    ['Bus Shelter', 'Unipole', 'Standard', 'Auto Branding', 'Pole Kiosk', 'Digital', 'Signal Post'],
                ],
                [
                    'board_type',
                    'Display Type',
                    'select',
                    ['Static', 'Digital'],
                ],
                [
                    'board_details',
                    'Board / Display Size Details',
                    'dimensions',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'brand_name',
                    'Brand Name',
                    'text',
                    [],
                ],
                [
                    'creative_contact_person',
                    'Contact Person',
                    'text',
                    [],
                ],
                [
                    'creative_mobile_number',
                    'Mobile Number',
                    'text',
                    [],
                ],
                [
                    'address',
                    'Address',
                    'textarea',
                    [],
                ],
                [
                    'company_details_document',
                    'Company Details Document',
                    'file',
                    [],
                ],
                [
                    'content_images',
                    'Creative Content / Assets',
                    'mediafiles',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'cutout_size_calculation' => [
                [
                    'outdoor_type',
                    'Outdoor Type',
                    'select',
                    ['Bus Shelter', 'Unipole', 'Standard', 'Auto Branding', 'Pole Kiosk', 'Digital', 'Signal Post'],
                ],
                [
                    'board_details',
                    'Board / Display Size Details',
                    'dimensions',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'hoarding_artwork',
                    'Creative / Artwork',
                    'file',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
        ],
        'roadshow' => [
            'creative_adaptation_requirements' => [
                [
                    'roadshow_subtype',
                    'Road Show Type',
                    'select',
                    ['Creative Adaptation', '3D Mockup Creative Adaptation'],
                ],
                [
                    'vehicle_type',
                    'Vehicle Type',
                    'vehicle_select',
                    ['3 Side LED 14 feet', '3 Side LED 18 feet', '7x5 LED Hybrid 8 feet', 'Box Model Triangle Roof', 'Center Portion Triangle Roof', 'Center Portion Without Roof', 'L-Model Box Roof with Utility Room', 'L-Model Box Roof', 'L-Model Without Roof', 'L-Shape LED', 'Single Side LED 17 feet', 'Static Model'],
                ],
                [
                    'vehicle_quantity',
                    'Vehicle Quantity',
                    'number',
                    [],
                ],
                [
                    'location',
                    'Campaign Location',
                    'location',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'new_creative_design' => [
                [
                    'roadshow_subtype',
                    'Road Show Type',
                    'select',
                    ['New Creative Design', '3D Mockup New Creative Design'],
                ],
                [
                    'vehicle_type',
                    'Vehicle Type',
                    'vehicle_select',
                    ['3 Side LED 14 feet', '3 Side LED 18 feet', '7x5 LED Hybrid 8 feet', 'Box Model Triangle Roof', 'Center Portion Triangle Roof', 'Center Portion Without Roof', 'L-Model Box Roof with Utility Room', 'L-Model Box Roof', 'L-Model Without Roof', 'L-Shape LED', 'Single Side LED 17 feet', 'Static Model'],
                ],
                [
                    'vehicle_details',
                    'Vehicle Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'brand_details',
                    'Brand Details',
                    'textarea',
                    [],
                ],
                [
                    'brand_details_upload',
                    'Brand Details Upload',
                    'file',
                    [],
                ],
                [
                    'brand_name',
                    'Brand Name',
                    'text',
                    [],
                ],
                [
                    'creative_contact_person',
                    'Contact Person',
                    'text',
                    [],
                ],
                [
                    'creative_mobile_number',
                    'Mobile Number',
                    'text',
                    [],
                ],
                [
                    'address',
                    'Address',
                    'textarea',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'content_images',
                    'Creative Content / Assets',
                    'mediafiles',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
        ],
        'fixtures' => [
            'design_with_creative' => [
                [
                    'recce_report',
                    'Site Recce / Measurement Details',
                    'file',
                    [],
                ],
                [
                    'client_format_manual',
                    'Client Brand / Format Guidelines',
                    'file',
                    [],
                ],
                [
                    'fixture_details',
                    'Fixture Specifications / Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'design_without_creative' => [
                [
                    'recce_report',
                    'Site Recce / Measurement Details',
                    'file',
                    [],
                ],
                [
                    'client_format_manual',
                    'Client Brand / Format Guidelines',
                    'file',
                    [],
                ],
                [
                    'fixture_details',
                    'Fixture Specifications / Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'website_link',
                    'Client Website',
                    'url',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
        ],
        'signage' => [
            'mockup' => [
                [
                    'recce_report',
                    'Site Recce / Measurement Details',
                    'file',
                    [],
                ],
                [
                    'material_specifications',
                    'Material Details',
                    'file',
                    [],
                ],
                [
                    'client_format_manual',
                    'Client Brand / Format Guidelines',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'creative_adaptation' => [
                [
                    'recce_report',
                    'Site Recce / Measurement Details',
                    'file',
                    [],
                ],
                [
                    'material_specifications',
                    'Material Details',
                    'file',
                    [],
                ],
                [
                    'client_format_manual',
                    'Client Brand / Format Guidelines',
                    'file',
                    [],
                ],
                [
                    'dealer_details',
                    'Dealer / Location Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'new_creative' => [
                [
                    'recce_report',
                    'Site Recce / Measurement Details',
                    'file',
                    [],
                ],
                [
                    'material_specifications',
                    'Material Details',
                    'file',
                    [],
                ],
                [
                    'client_format_manual',
                    'Client Brand / Format Guidelines',
                    'file',
                    [],
                ],
                [
                    'dealer_details',
                    'Dealer / Location Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'technical_drawing' => [
                [
                    'recce_report',
                    'Site Recce / Measurement Details',
                    'file',
                    [],
                ],
                [
                    'client_format_manual',
                    'Client Brand / Format Guidelines',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'three_d_design' => [
                [
                    'recce_report',
                    'Site Recce / Measurement Details',
                    'file',
                    [],
                ],
                [
                    'technical_drawing',
                    'Technical Drawing',
                    'file',
                    [],
                ],
                [
                    'client_format_manual',
                    'Client Brand / Format Guidelines',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'technical_and_three_d' => [
                [
                    'recce_report',
                    'Site Recce / Measurement Details',
                    'file',
                    [],
                ],
                [
                    'client_format_manual',
                    'Client Brand / Format Guidelines',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
        ],
        'pop_offsets' => [
            'mockup_design' => [
                [
                    'product_type',
                    'Print / Product Type',
                    'select',
                    ['Leaflets', 'Poster', 'Brochure', 'Visiting Card', 'Pocket Card', 'Dangler', 'Roll Up Standee', 'Sunpack Sheet', 'Calendar', 'ID Card', 'Other'],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'design_adaptation' => [
                [
                    'product_type',
                    'Print / Product Type',
                    'select',
                    ['Leaflets', 'Poster', 'Brochure', 'Visiting Card', 'Pocket Card', 'Dangler', 'Roll Up Standee', 'Sunpack Sheet', 'Calendar', 'ID Card', 'Other'],
                ],
                [
                    'size_details',
                    'Print Size Details',
                    'sizes',
                    [],
                ],
                [
                    'element_list',
                    'Print / Element Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'creative_design' => [
                [
                    'product_type',
                    'Print / Product Type',
                    'select',
                    ['Leaflets', 'Poster', 'Brochure', 'Visiting Card', 'Pocket Card', 'Dangler', 'Roll Up Standee', 'Sunpack Sheet', 'Calendar', 'ID Card', 'Other'],
                ],
                [
                    'size_details',
                    'Print Size Details',
                    'sizes',
                    [],
                ],
                [
                    'element_list',
                    'Print / Element Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
        ],
        'events_activations' => [
            'proposal_designs' => [
                [
                    'location',
                    'Event / Activation Location',
                    'location',
                    [],
                ],
                [
                    'requirement_list',
                    'Event / Activation Requirement Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'element_design_with_creative' => [
                [
                    'location',
                    'Event / Activation Location',
                    'location',
                    [],
                ],
                [
                    'recce_report',
                    'Venue / Site Recce Details',
                    'file',
                    [],
                ],
                [
                    'brand_guidelines',
                    'Brand Guidelines',
                    'file',
                    [],
                ],
                [
                    'requirement_list',
                    'Event / Activation Requirement Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'file',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'element_design_without_creative' => [
                [
                    'location',
                    'Event / Activation Location',
                    'location',
                    [],
                ],
                [
                    'recce_report',
                    'Venue / Site Recce Details',
                    'file',
                    [],
                ],
                [
                    'brand_guidelines',
                    'Brand Guidelines',
                    'file',
                    [],
                ],
                [
                    'requirement_list',
                    'Event / Activation Requirement Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'logo_images',
                    'Logo / Brand Assets',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'three_d_layout' => [
                [
                    'location',
                    'Event / Activation Location',
                    'location',
                    [],
                ],
                [
                    'recce_report',
                    'Venue / Site Recce Details',
                    'file',
                    [],
                ],
                [
                    'brand_guidelines',
                    'Brand Guidelines',
                    'file',
                    [],
                ],
                [
                    'requirement_list',
                    'Event / Activation Requirement Details',
                    'file',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
        ],
        'media' => [
            'creative_adaptation' => [
                [
                    'media_type',
                    'Media Type',
                    'select',
                    ['Theatre Ads', 'Newspaper Ads', 'TV Ads'],
                ],
                [
                    'creative_size_details',
                    'Creative Size Details',
                    'media_sizes',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'own_creative' => [
                [
                    'media_type',
                    'Media Type',
                    'select',
                    ['Theatre Ads', 'Newspaper Ads', 'TV Ads'],
                ],
                [
                    'creative_size_details',
                    'Creative Size Details',
                    'media_sizes',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative / Sample Assets',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'theatre_ads' => [
                [
                    'media_type',
                    'Media Type',
                    'select',
                    ['Theatre Ads', 'Newspaper Ads', 'TV Ads'],
                ],
                [
                    'creative_size_details',
                    'Creative Size Details',
                    'media_sizes',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'newspaper_ads' => [
                [
                    'media_type',
                    'Media Type',
                    'select',
                    ['Theatre Ads', 'Newspaper Ads', 'TV Ads'],
                ],
                [
                    'creative_size_details',
                    'Creative Size Details',
                    'media_sizes',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
            'tv_ads' => [
                [
                    'media_type',
                    'Media Type',
                    'select',
                    ['Theatre Ads', 'Newspaper Ads', 'TV Ads'],
                ],
                [
                    'creative_size_details',
                    'Creative Size Details',
                    'media_sizes',
                    [],
                ],
                [
                    'description',
                    'Description',
                    'textarea',
                    [],
                ],
                [
                    'company_details',
                    'Company Details',
                    'textarea',
                    [],
                ],
                [
                    'company_details_upload',
                    'Company Details Upload',
                    'files',
                    [],
                ],
                [
                    'creative',
                    'Creative',
                    'files',
                    [],
                ],
                [
                    'reference_notes',
                    'References',
                    'textarea',
                    [],
                ],
                [
                    'attachments',
                    'Attachments',
                    'files',
                    [],
                ],
                [
                    'client_audio',
                    'Audio Reference',
                    'audio',
                    [],
                ],
            ],
        ],
    ];

    public function edit(Request $request, DesignTask $task): View
    {
        $this->authorizeBdTask($request, $task);
        $this->assertTaskIsEditable($task);

        $task->loadMissing(['designer:id,name', 'assigner:id,name']);

        return view('bd.tasks.edit', [
            'task' => $task,
            'requirementFields' => $this->requirementFieldsFor($task),
            'requirementAttachmentGroups' => $this->collectRequirementAttachments($task->requirements ?? []),
            'minDueDate' => now()->format('Y-m-d\TH:i'),
            'maxDueDate' => $this->maximumAllowedDueDate()->format('Y-m-d\TH:i'),
            'completedCreatives' => app(DesignTaskProgressService::class)->completed($task),
        ]);
    }

    public function update(Request $request, DesignTask $task)
    {
        $this->authorizeBdTask($request, $task);
        $this->assertTaskIsEditable($task);

        $completedCreatives = app(DesignTaskProgressService::class)->completed($task);
        $allowedRequirementFields = collect($this->requirementFieldsFor($task))->keyBy('key');

        $data = $request->validate([
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_at' => [
                'required',
                'date',
                'after_or_equal:today',
                'before_or_equal:'.$this->maximumAllowedDueDate()->format('Y-m-d H:i:s'),
            ],
            'total_creatives' => [
                'required',
                'integer',
                'min:1',
                'max:9999',
                function (string $attribute, mixed $value, \Closure $fail) use ($completedCreatives): void {
                    if ((int) $value < $completedCreatives) {
                        $fail('Creative Count cannot be lower than the '.$completedCreatives.' creative(s) already completed.');
                    }
                },
            ],
            'requirements' => ['nullable', 'array'],
            'requirements.board_details' => ['nullable', 'array'],
            'requirements.board_details.*.name' => ['nullable', 'string', 'max:180'],
            'requirements.board_details.*.width' => ['nullable', 'numeric', 'min:0'],
            'requirements.board_details.*.height' => ['nullable', 'numeric', 'min:0'],
            'requirements.board_details.*.area' => ['nullable', 'numeric', 'min:0'],
            'requirements.board_details.*.unit' => ['nullable', 'string', 'max:30'],
            'requirements.size_details' => ['nullable', 'array'],
            'requirements.size_details.*.name' => ['nullable', 'string', 'max:180'],
            'requirements.size_details.*.width' => ['nullable', 'numeric', 'min:0'],
            'requirements.size_details.*.height' => ['nullable', 'numeric', 'min:0'],
            'requirements.size_details.*.area' => ['nullable', 'numeric', 'min:0'],
            'requirements.size_details.*.unit' => ['nullable', 'string', 'max:30'],
            'requirements.creative_size_details' => ['nullable', 'array'],
            'requirements.creative_size_details.*.name' => ['nullable', 'string', 'max:180'],
            'requirements.creative_size_details.*.width' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'requirements.creative_size_details.*.height' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'requirements.creative_size_details.*.ratio' => ['nullable', 'string', 'max:100'],
            'remove_requirement_files' => ['nullable', 'array'],
            'remove_requirement_files.*' => ['nullable', 'array'],
            'remove_requirement_files.*.*' => ['string'],
            'new_requirement_files' => ['nullable', 'array'],
            'new_requirement_files.*' => ['nullable', 'array', 'max:10'],
            'new_requirement_files.*.*' => ['file', 'max:102400'],
        ]);

        $batchId = (string) Str::uuid();
        $historyRows = [];

        DB::transaction(function () use (
            $task,
            $data,
            $request,
            $batchId,
            $allowedRequirementFields,
            &$historyRows
        ): void {
            $task->refresh();
            $this->assertTaskIsEditable($task);

            $coreUpdates = [];

            foreach (self::EDITABLE_CORE_FIELDS as $field => $label) {
                $oldRaw = $task->{$field};
                $newRaw = $data[$field];

                if ($field === 'due_at') {
                    $oldComparable = optional($task->due_at)->format('Y-m-d H:i:s');
                    $newComparable = date('Y-m-d H:i:s', strtotime((string) $newRaw));
                } else {
                    $oldComparable = (string) ($oldRaw ?? '');
                    $newComparable = (string) ($newRaw ?? '');
                }

                if ($oldComparable === $newComparable) {
                    continue;
                }

                $coreUpdates[$field] = $newRaw;
                $historyRows[] = [
                    'design_task_id' => $task->id,
                    'edited_by' => $request->user()->id,
                    'edit_batch_id' => $batchId,
                    'field_name' => $label,
                    'old_value' => $this->displayValue($field, $oldRaw),
                    'new_value' => $this->displayValue($field, $newRaw),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $oldRequirements = $task->requirements ?? [];
            $newRequirements = $oldRequirements;

            foreach (($data['requirements'] ?? []) as $key => $submittedValue) {
                if (! $allowedRequirementFields->has((string) $key)) {
                    continue;
                }

                $fieldType = (string) data_get($allowedRequirementFields->get((string) $key), 'type', 'text');
                if (in_array($fieldType, ['file', 'files', 'mediafiles', 'audio'], true)) {
                    continue;
                }

                if (in_array((string) $key, ['board_details', 'size_details', 'creative_size_details'], true) && is_array($submittedValue)) {
                    $submittedValue = $this->normalizeStructuredRows($submittedValue);
                }

                $oldValue = $oldRequirements[$key] ?? null;
                if (in_array((string) $key, ['board_details', 'size_details', 'creative_size_details'], true) && is_array($oldValue)) {
                    $oldValue = $this->normalizeStructuredRows($oldValue);
                }

                if ($this->valuesEqual($oldValue, $submittedValue)) {
                    continue;
                }

                $newRequirements[$key] = $submittedValue;

                $historyRows[] = [
                    'design_task_id' => $task->id,
                    'edited_by' => $request->user()->id,
                    'edit_batch_id' => $batchId,
                    'field_name' => 'Requirement · '.Str::headline((string) $key),
                    'old_value' => $this->stringify($oldValue),
                    'new_value' => $this->stringify($submittedValue),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $removedFiles = collect($request->input('remove_requirement_files', []));
            foreach ($removedFiles as $key => $paths) {
                $definition = $allowedRequirementFields->get((string) $key);
                if (! $definition || ! in_array((string) ($definition['type'] ?? ''), ['file', 'files', 'mediafiles', 'audio'], true)) {
                    continue;
                }

                $paths = array_values(array_filter((array) $paths, 'is_string'));
                if ($paths === []) {
                    continue;
                }

                $oldValue = $newRequirements[$key] ?? null;
                $newRequirements[$key] = $this->removeStoredPaths($oldValue, $paths);

                foreach ($paths as $path) {
                    Storage::disk('spaces')->delete($path);
                    $historyRows[] = [
                        'design_task_id' => $task->id,
                        'edited_by' => $request->user()->id,
                        'edit_batch_id' => $batchId,
                        'field_name' => 'Attachment · '.Str::headline((string) $key),
                        'old_value' => basename($path),
                        'new_value' => 'Removed',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            foreach ((array) $request->file('new_requirement_files', []) as $key => $files) {
                $definition = $allowedRequirementFields->get((string) $key);
                if (! $definition || ! in_array((string) ($definition['type'] ?? ''), ['file', 'files', 'mediafiles', 'audio'], true)) {
                    continue;
                }

                $files = array_values(array_filter((array) $files, fn ($file) => $file instanceof UploadedFile));
                if ($files === []) {
                    continue;
                }

                $storedPaths = [];
                foreach ($files as $index => $file) {
                    $storedPaths[] = $this->storeEditAttachment($task, (string) $key, $file, $index + 1);
                }

                $newRequirements[$key] = $this->appendStoredPaths($newRequirements[$key] ?? [], $storedPaths);

                foreach ($storedPaths as $index => $path) {
                    $historyRows[] = [
                        'design_task_id' => $task->id,
                        'edited_by' => $request->user()->id,
                        'edit_batch_id' => $batchId,
                        'field_name' => 'Attachment · '.Str::headline((string) $key),
                        'old_value' => '—',
                        'new_value' => $files[$index]->getClientOriginalName(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($coreUpdates !== []) {
                $task->fill($coreUpdates);
            }

            if ($newRequirements !== $oldRequirements) {
                $task->requirements = $newRequirements;
            }

            if ($task->isDirty()) {
                $task->save();
            }

            if ($historyRows !== []) {
                DesignTaskEditHistory::query()->insert($historyRows);
            }
        });

        if ($historyRows === []) {
            return redirect()
                ->route('bd.tasks.show', $task)
                ->with('success', 'No changes were detected.');
        }

        return redirect()
            ->route('bd.tasks.show', ['task' => $task, 'tab' => 'history'])
            ->with('success', 'Task updated successfully. Edit History has been recorded.');
    }

    private function authorizeBdTask(Request $request, DesignTask $task): void
    {
        abort_unless(
            $request->user()?->role === 'bd'
            && (int) $task->assigned_by === (int) $request->user()->id,
            403
        );
    }

    private function assertTaskIsEditable(DesignTask $task): void
    {
        abort_if(
            in_array($task->status, self::LOCKED_EDIT_STATUSES, true),
            403,
            'Tasks in Waiting for Confirmation, Rework or Completed cannot be edited.'
        );
    }

    private function requirementFieldsFor(DesignTask $task): array
    {
        return collect(self::REQUIREMENT_FIELDS[$task->vertical][$task->task_nature] ?? [])
            ->map(fn (array $field): array => [
                'key' => $field[0],
                'label' => $field[1],
                'type' => $field[2],
                'options' => $field[3] ?? [],
            ])
            ->values()
            ->all();
    }

    private function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'due_at') {
            try {
                return Carbon::parse($value)->format('d M Y');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        if ($field === 'priority') {
            return ucfirst((string) $value);
        }

        return $this->stringify($value);
    }

    private function normalizeStructuredRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                $name = trim((string) ($row['name'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? 'feet'));

                $normalizeNumber = static function (mixed $value): int|float|string {
                    if ($value === null || $value === '') {
                        return '';
                    }

                    $number = (float) $value;
                    return floor($number) === $number ? (int) $number : $number;
                };

                $normalized = [
                    'name' => $name,
                    'width' => $normalizeNumber($row['width'] ?? ''),
                    'height' => $normalizeNumber($row['height'] ?? ''),
                ];

                if (array_key_exists('ratio', $row)) {
                    $normalized['ratio'] = trim((string) ($row['ratio'] ?? ''));
                } else {
                    $normalized['area'] = $normalizeNumber($row['area'] ?? $row['square_feet'] ?? '');
                    $normalized['unit'] = $unit !== '' ? $unit : 'feet';
                }

                return $normalized;
            })
            ->filter(fn (array $row) =>
                $row['name'] !== ''
                || $row['width'] !== ''
                || $row['height'] !== ''
                || ($row['area'] ?? '') !== ''
                || ($row['ratio'] ?? '') !== ''
            )
            ->values()
            ->all();
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private function valuesEqual(mixed $oldValue, mixed $newValue): bool
    {
        return $this->stringify($oldValue) === $this->stringify($newValue);
    }

    private function maximumAllowedDueDate(): Carbon
    {
        $date = now()->copy()->startOfDay();
        $workingDaysAdded = 0;

        while ($workingDaysAdded < 7) {
            $date->addDay();

            if (! $date->isWeekend()) {
                $workingDaysAdded++;
            }
        }

        return $date->endOfDay();
    }

    private function collectRequirementAttachments(array $requirements): array
    {
        $groups = [];

        foreach ($requirements as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            $paths = [];
            $this->extractStoredPaths($value, $paths);
            if ($paths === []) {
                continue;
            }

            $groups[(string) $key] = [
                'key' => (string) $key,
                'label' => Str::headline((string) $key),
                'files' => collect($paths)->unique()->map(fn (string $path) => [
                    'path' => $path,
                    'name' => basename($path),
                    'url' => Storage::disk('spaces')->url($path),
                ])->values()->all(),
            ];
        }

        return $groups;
    }

    private function extractStoredPaths(mixed $value, array &$paths): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->extractStoredPaths($item, $paths);
            }
            return;
        }

        if (is_string($value) && $this->looksLikeStoredFile($value)) {
            $paths[] = $value;
        }
    }

    private function looksLikeStoredFile(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        return str_contains($value, '/') && (bool) pathinfo($value, PATHINFO_EXTENSION);
    }

    private function removeStoredPaths(mixed $value, array $paths): mixed
    {
        if (is_array($value)) {
            $cleaned = [];
            foreach ($value as $key => $item) {
                $result = $this->removeStoredPaths($item, $paths);
                if ($result === null || $result === [] || $result === '') {
                    continue;
                }
                $cleaned[$key] = $result;
            }

            return array_is_list($value) ? array_values($cleaned) : $cleaned;
        }

        if (is_string($value) && in_array($value, $paths, true)) {
            return null;
        }

        return $value;
    }

    private function appendStoredPaths(mixed $value, array $paths): array
    {
        if ($value === null || $value === '') {
            return $paths;
        }

        if (is_string($value)) {
            return array_values(array_unique(array_merge([$value], $paths)));
        }

        if (is_array($value) && array_is_list($value)) {
            return array_values(array_unique(array_merge($value, $paths), SORT_REGULAR));
        }

        return array_merge([$value], $paths);
    }

    private function storeEditAttachment(DesignTask $task, string $key, UploadedFile $file, int $sequence): string
    {
        $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
        $directory = implode('/', [
            $root,
            now()->format('Y'),
            Str::slug(str_replace('_', '-', $task->vertical)),
            $task->task_id.'_'.Str::slug($task->task_name),
            Str::slug(str_replace('_', '-', $task->task_nature)),
            'edit-attachments',
            Str::slug($key),
        ]);

        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'attachment';
        $extension = strtolower($file->getClientOriginalExtension());
        $fileName = $task->task_id.'__'.Str::slug($key).'__'.now()->format('Ymd-His-v').'__'.$sequence.'__'.$base.($extension ? '.'.$extension : '');

        return $file->storePubliclyAs($directory, $fileName, 'spaces');
    }

}
