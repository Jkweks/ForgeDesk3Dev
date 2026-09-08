<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CompanySettingController extends Controller
{
    public function show()
    {
        return response()->json(CompanySetting::current());
    }

    public function uploadLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:png,jpg,jpeg,gif,webp|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $setting = CompanySetting::current();

        if ($setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
        }

        $path = $request->file('logo')->store('company', 'public');
        $setting->update(['logo_path' => $path]);

        return response()->json([
            'message' => 'Logo updated',
            'company_setting' => $setting->fresh(),
        ]);
    }

    public function deleteLogo()
    {
        $setting = CompanySetting::current();

        if ($setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
            $setting->update(['logo_path' => null]);
        }

        return response()->json(['message' => 'Logo removed', 'company_setting' => $setting->fresh()]);
    }
}
