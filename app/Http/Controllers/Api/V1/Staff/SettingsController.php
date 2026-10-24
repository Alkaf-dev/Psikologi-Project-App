<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends ApiController
{
    public function show(SettingsRepository $settings): JsonResponse
    {
        return response()->json(['data' => $settings->allKeyed()]);
    }

    public function update(Request $request, SettingsRepository $settings): JsonResponse
    {
        $data = $request->validate([
            'program_name' => ['sometimes', 'required', 'string', 'max:120'],
            'academic_year' => ['sometimes', 'nullable', 'string', 'max:20'],
            'semester' => ['sometimes', 'nullable', 'string', 'max:20'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'public_tagline' => ['sometimes', 'nullable', 'string', 'max:180'],
            'public_about' => ['sometimes', 'nullable', 'string'],
            'portal_note' => ['sometimes', 'nullable', 'string', 'max:180'],
        ]);

        $settings->setMany($data);

        return response()->json([
            'data' => $settings->allKeyed(),
            'message' => 'Pengaturan berhasil disimpan.',
        ]);
    }
}
