<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CutFlow\CutFlowSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Lets a settings.edit admin configure the tiger-bridge connection (URL,
 * shared token, fake mode) and the cut-station tablet IP allowlist from the
 * app's System Settings tab, instead of editing the .env file on the server
 * — see App\Services\CutFlow\TigerBridgeClient and
 * App\Models\CutFlow\CutFlowSetting.
 */
class CutFlowBridgeSettingController extends Controller
{
    public function show()
    {
        $settings = CutFlowSetting::current();

        return response()->json([
            'bridge_url' => $settings->bridgeUrl(),
            'bridge_fake' => $settings->bridgeFake(),
            'bridge_token_set' => $settings->bridgeToken() !== '',
            'tablet_allowed_ips' => $settings->tabletAllowedIps(),
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bridge_url' => 'nullable|string|max:255',
            'bridge_token' => 'nullable|string|max:255',
            'bridge_fake' => 'boolean',
            'tablet_allowed_ips' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $settings = CutFlowSetting::current();

        $update = [
            'bridge_url' => $data['bridge_url'] ?? null,
            'bridge_fake' => $data['bridge_fake'] ?? false,
            'tablet_allowed_ips' => $data['tablet_allowed_ips'] ?? null,
        ];

        // Blank token in the request means "leave the stored one alone" —
        // the field is never sent back to the browser once set, so a bare
        // save from the form must not wipe it.
        if (array_key_exists('bridge_token', $data) && $data['bridge_token'] !== null && $data['bridge_token'] !== '') {
            $update['bridge_token'] = $data['bridge_token'];
        }

        $settings->update($update);

        return response()->json([
            'message' => 'CutFlow bridge settings saved',
            'bridge_url' => $settings->bridgeUrl(),
            'bridge_fake' => $settings->bridgeFake(),
            'bridge_token_set' => $settings->bridgeToken() !== '',
            'tablet_allowed_ips' => $settings->tabletAllowedIps(),
        ]);
    }
}
