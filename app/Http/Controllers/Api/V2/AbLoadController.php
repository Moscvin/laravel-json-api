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

        // Build base query for stats
        $statsQuery = AbLoad::query();
        if ($user->type !== 'root') {
            $statsQuery->where('uid', $user->id);
        }

        // Get counts by status
        $statusCounts = $statsQuery->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalLoads = $statsQuery->count();

        return response()->json([
            'loads' => $loads,
            'stats' => [
                'total' => $totalLoads,
                'by_status' => $statusCounts,
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Get single load by ID from body
     */
    public function show(Request $request): JsonResponse
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

        if (!$request->filled('id')) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Validation Error',
                        'detail' => 'The id field is required',
                        'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                    ]
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $load = AbLoad::find($request->input('id'));

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
     * Update load (user can update own loads, root can update all)
     */
    public function update(Request $request, $id): JsonResponse
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

        // Check authorization - user can edit own loads, root can edit all
        if ($user->type !== 'root' && $load->uid !== $user->id) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Not authorized to update this load',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $request->all();

        $allowedStatuses = [
            'Status Not Set',
            'In Sales Process',
            'Sale Closed',
            'Picked Up',
            'Delivered'
        ];

        $rules = [
            'status' => 'sometimes|string|in:' . implode(',', $allowedStatuses),
            'manual_rate' => 'sometimes|numeric|min:0',
            'gate_check_in' => 'nullable|string|max:255',
            'manual_pull_date' => 'nullable|string|max:255',
            'manual_pull_time' => 'nullable|string|max:255',
            'manual_delivery_date' => 'nullable|string|max:255',
            'manual_delivery_time' => 'nullable|string|max:255',
            'manual_delivery_date_2' => 'nullable|string|max:255',
            'manual_delivery_time_2' => 'nullable|string|max:255',
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
            // Prepare data for update
            $updateData = [];

            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if (isset($data['manual_rate'])) {
                $updateData['manual_rate'] = $data['manual_rate'];
            }
            if (isset($data['gate_check_in'])) {
                $updateData['gate_check_in'] = $data['gate_check_in'];
            }
            if (isset($data['manual_pull_date'])) {
                $updateData['manual_pull_date'] = $data['manual_pull_date'];
            }
            if (isset($data['manual_pull_time'])) {
                $updateData['manual_pull_time'] = $data['manual_pull_time'];
            }
            if (isset($data['manual_delivery_date'])) {
                $updateData['manual_delivery_date'] = $data['manual_delivery_date'];
            }
            if (isset($data['manual_delivery_time'])) {
                $updateData['manual_delivery_time'] = $data['manual_delivery_time'];
            }
            if (isset($data['manual_delivery_date_2'])) {
                $updateData['manual_delivery_date_2'] = $data['manual_delivery_date_2'];
            }
            if (isset($data['manual_delivery_time_2'])) {
                $updateData['manual_delivery_time_2'] = $data['manual_delivery_time_2'];
            }

            $load->fill($updateData);
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

    /**
     * Get available status values
     */
    public function getStatuses(): JsonResponse
    {
        return response()->json([
            'statuses' => [
                'Status Not Set',
                'In Sales Process',
                'Sale Closed',
                'Picked Up',
                'Delivered'
            ]
        ], Response::HTTP_OK);
    }
}
