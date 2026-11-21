<?php

namespace App\Configs\Social\Http;

use App\Configs\Social\Support\SocialAccess;
use App\Configs\Social\Support\SocialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Sitebill\AppSdk\Exceptions\FilesException;
use Sitebill\AppSdk\Host;

/** Загрузка картинки поста/аватара. Путь из ответа потом передаётся в мутацию (она проверяет префикс каталога). */
class UploadController
{
    public function store(Request $request): JsonResponse
    {
        if (! SocialAccess::isMember(Auth::user())) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $request->validate(['image' => ['required', 'file', 'max:5120']]);
        try {
            return response()->json(Host::files()->storeImage(SocialService::APP_ID, $request->file('image')));
        } catch (FilesException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
