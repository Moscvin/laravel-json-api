<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\AbLoad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AbLoadController extends Controller
{
    /**
     * Format AbLoad data
     */
    private function formatLoadData($load): array
    {
        return [
            'id' => $load->id,
            'uid' => $load->uid,
            'commodity' => $load->commodity,
            'status' => $load->status,
            'status_label' => $load->status_label,
            'pull_date' => $load->pull_date,
            'pull_time' => $load->pull_time,
            'pull_datetime' => $load->pull_datetime ? $load->pull_datetime->format('Y-m-d H:i:s') : null,
            'manual_pull_date' => $load->manual_pull_date,
            'manual_pull_time' => $load->manual_pull_time,
            'manual_pull_datetime' => $load->manual_pull_datetime ? $load->manual_pull_datetime->format('Y-m-d H:i:s') : null,
            'stop_1_consignee' => $load->consignee,
            'stop_1_delivery_date' => $load->delivery_date,
            'stop_1_delivery_time' => $load->delivery_time,
            'stop_1_delivery_datetime' => $load->delivery_datetime ? $load->delivery_datetime->format('Y-m-d H:i:s') : null,
            'stop_1_manual_delivery_date' => $load->manual_delivery_date,
            'stop_1_manual_delivery_time' => $load->manual_delivery_time,
            'stop_1_manual_delivery_datetime' => $load->manual_delivery_datetime ? $load->manual_delivery_datetime->format('Y-m-d H:i:s') : null,
            'stop_2_consignee' => $load->consignee_2,
            'stop_2_delivery_date' => $load->delivery_date_2,
            'stop_2_delivery_time' => $load->delivery_time_2,
            'stop_2_delivery_datetime' => $load->delivery_datetime_2 ? $load->delivery_datetime_2->format('Y-m-d H:i:s') : null,
            'stop_2_manual_delivery_date' => $load->manual_delivery_date_2,
            'stop_2_manual_delivery_time' => $load->manual_delivery_time_2,
            'stop_2_manual_delivery_datetime' => $load->manual_delivery_datetime_2 ? $load->manual_delivery_datetime_2->format('Y-m-d H:i:s') : null,
            'gate_check_in' => $load->gate_check_in,
            'rate' => $load->rate,
            'manual_rate' => $load->manual_rate,
            'comment' => $load->comment,
            'load_id' => $load->load_id,
            'shipper' => $load->shipper,
            'created_at' => $load->created_at ? $load->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $load->updated_at ? $load->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * Get all loads for authenticated user (root sees all)
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Unauthorized',
                        'detail' => 'Authentication required',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        $query = AbLoad::query();

        // If not root, filter by current user
        if ($user->type !== 'root') {
            $query->where('uid', $user->id);
        }

        // Optional filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('load_id')) {
            $query->where('load_id', 'like', '%' . $request->input('load_id') . '%');
        }

        if ($request->filled('commodity')) {
            $query->where('commodity', 'like', '%' . $request->input('commodity') . '%');
        }

        $loads = $query->orderBy('created_at', 'desc')->get()->map(function ($load) {
            return $this->formatLoadData($load);
        });

        $totalLoads = $query->count();
        $statuses = AbLoad::select('status')->distinct()->pluck('status')->toArray();

        return response()->json([
            'loads' => $loads,
            'stats' => [
                'total' => $totalLoads,
                'statuses' => $statuses,
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Get single load by ID
     */
    public function show($id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Unauthorized',
                        'detail' => 'Authentication required',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        $load = AbLoad::find($id);

        if (!$load) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Not Found',
                        'detail' => 'Load not found',
                        'status' => Response::HTTP_NOT_FOUND,
                    ]
                ]
            ], Response::HTTP_NOT_FOUND);
        }

        // Check authorization - user can see own loads, root sees all
        if ($user->type !== 'root' && $load->uid !== $user->id) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Not authorized to view this load',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'load' => $this->formatLoadData($load)
        ], Response::HTTP_OK);
    }

    /**
     * Update load (root only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Only root users can update loads',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $load = AbLoad::find($id);

        if (!$load) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Not Found',
                        'detail' => 'Load not found',
                        'status' => Response::HTTP_NOT_FOUND,
                    ]
                ]
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->all();

        $rules = [
            'status' => 'sometimes|string|max:255',
            'commodity' => 'sometimes|string|max:255',
            'rate' => 'sometimes|numeric|min:0',
            'manual_rate' => 'sometimes|numeric|min:0',
            'comment' => 'nullable|string|max:255',
            'gate_check_in' => 'nullable|string|max:255',
            'pull_date' => 'nullable|string|max:255',
            'pull_time' => 'nullable|string|max:255',
            'pull_datetime' => 'nullable|date_format:Y-m-d H:i:s',
            'manual_pull_date' => 'nullable|string|max:255',
            'manual_pull_time' => 'nullable|string|max:255',
            'manual_pull_datetime' => 'nullable|date_format:Y-m-d H:i:s',
            'consignee' => 'nullable|string|max:255',
            'delivery_date' => 'nullable|string|max:255',
            'delivery_time' => 'nullable|string|max:255',
            'delivery_datetime' => 'nullable|date_format:Y-m-d H:i:s',
            'manual_delivery_date' => 'nullable|string|max:255',
            'manual_delivery_time' => 'nullable|string|max:255',
            'manual_delivery_datetime' => 'nullable|date_format:Y-m-d H:i:s',
            'consignee_2' => 'nullable|string|max:255',
            'delivery_date_2' => 'nullable|string|max:255',
            'delivery_time_2' => 'nullable|string|max:255',
            'delivery_datetime_2' => 'nullable|date_format:Y-m-d H:i:s',
            'manual_delivery_date_2' => 'nullable|string|max:255',
            'manual_delivery_time_2' => 'nullable|string|max:255',
            'manual_delivery_datetime_2' => 'nullable|date_format:Y-m-d H:i:s',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'errors' => collect($validator->errors()->messages())->map(function ($errors, $field) {
                    return [
                        'title' => 'Validation Error',
                        'detail' => implode(', ', $errors),
                        'source' => ['pointer' => "/data/attributes/{$field}"],
                        'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                    ];
                })->values()->all()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Update only provided fields
            foreach ($rules as $field => $rule) {
                if (isset($data[$field])) {
                    $load->{$field} = $data[$field];
                }
            }

            $load->save();

            return response()->json([
                'load' => $this->formatLoadData($load),
                'message' => 'Load updated successfully.'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Error',
                        'detail' => 'Failed to update load: ' . $e->getMessage(),
                        'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    ]
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
