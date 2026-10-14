<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\JsonResponse;

class RoosterVergrendelController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json(['vergrendeld' => Option::getValue('rooster_vergrendeld') === '1']);
    }

    public function vergrendel(): JsonResponse
    {
        Option::setValue('rooster_vergrendeld', '1');

        return response()->json(['message' => __('api.beheer.rooster_locked_status'), 'vergrendeld' => true]);
    }

    public function ontgrendel(): JsonResponse
    {
        Option::setValue('rooster_vergrendeld', '0');

        return response()->json(['message' => __('api.beheer.rooster_unlocked_status'), 'vergrendeld' => false]);
    }
}
