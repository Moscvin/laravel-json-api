<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\LoadSmart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadSmartController extends Controller
{
    /**
     * Get LoadSmart with LoadSmartChanging by id_load_smart
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'errors' => [[
                    'title' => 'Unauthorized',
                    'detail' => 'Authentication required',
                    'status' => Response::HTTP_UNAUTHORIZED,
                ]]
            ], Response::HTTP_UNAUTHORIZED);
        }

        $idLoad = $request->input('id_load');
        if (!$idLoad) {
            return response()->json([
                'errors' => [[
                    'title' => 'Bad Request',
                    'detail' => 'id_load is required',
                    'status' => Response::HTTP_BAD_REQUEST,
                ]]
            ], Response::HTTP_BAD_REQUEST);
        }

        $loadSmart = LoadSmart::with('changes')->where('id_load_smart', $idLoad)->first();
        if (!$loadSmart) {
            return response()->json([
                'errors' => [[
                    'title' => 'Not Found',
                    'detail' => 'LoadSmart not found',
                    'status' => Response::HTTP_NOT_FOUND,
                ]]
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $loadSmart
        ], Response::HTTP_OK);
    }

    /**
     * Get all LoadSmart with LoadSmartChanging
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'errors' => [[
                    'title' => 'Unauthorized',
                    'detail' => 'Authentication required',
                    'status' => Response::HTTP_UNAUTHORIZED,
                ]]
            ], Response::HTTP_UNAUTHORIZED);
        }

        $loads = LoadSmart::with('changes')->get();
        return response()->json([
            'data' => $loads
        ], Response::HTTP_OK);
    }
}
