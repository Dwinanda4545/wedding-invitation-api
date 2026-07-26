<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvitationThemeStoreRequest;
use App\Http\Requests\InvitationThemeUpdateRequest;
use App\Models\InvitationTheme;

class InvitationThemeController extends Controller
{
    public function index()
    {
        $themes = InvitationTheme::query()
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $themes]);
    }

    public function store(InvitationThemeStoreRequest $request)
    {
        $theme = InvitationTheme::create($request->validated());

        return response()->json(['data' => $theme], 201);
    }

    public function update(InvitationThemeUpdateRequest $request, InvitationTheme $invitationTheme)
    {
        $invitationTheme->update($request->validated());

        return response()->json(['data' => $invitationTheme->fresh()]);
    }

    public function destroy(InvitationTheme $invitationTheme)
    {
        $invitationTheme->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
