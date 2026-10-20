<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Complaint;
use App\Models\Order;
use App\Traits\ApiResponse;

#[Group('Complaints', "Shikoyatlar: mijoz yuboradi, ega yoki menejer javob beradi, mijoz moderatorga yuboradi.")]
class ComplaintController extends Controller
{
    use ApiResponse;

    /**
     * Get list of complaints based on user role.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = $user->role->value ?? null; // null for an in-memory user created without a role

        if (in_array($userRole, ['owner', 'manager'])) {
            $query = Complaint::with(['order', 'user'])
                ->whereHas('order', function ($q) use ($user, $userRole) {
                    $q->where('store_id', $user->store_id);
                    if ($userRole === 'manager' && $user->branch_id) {
                        $q->where('branch_id', $user->branch_id);
                    }
                });
        } else {
            // Customer
            $query = Complaint::with(['order'])
                ->where('user_id', $user->id);
        }

        return $this->success($query->latest()->get());
    }

    /**
     * Customer files a complaint.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'type' => 'required|string|in:WRONG_ITEM,ORDER_NOT_RECEIVED,QUALITY_ISSUE,COURIER_ISSUE,PAYMENT_ISSUE,OTHER',
            'description' => 'required|string|max:1000',
        ]);

        $order = Order::find($request->order_id);

        // Ownership validation: must belong to the customer
        if ($order->user_id !== $user->id) {
            return $this->error('Ruxsat etilmagan buyurtma.', [], 403);
        }

        $complaint = Complaint::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => $request->type,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return $this->success($complaint, 'Shikoyat muvaffaqiyatli yuborildi.', 201);
    }

    /**
     * Owner or Manager replies to the complaint.
     */
    public function reply(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = $user->role->value ?? null; // null for an in-memory user created without a role
        if (!in_array($userRole, ['owner', 'manager'])) {
            return $this->error('Ruxsat etilmagan.', [], 403);
        }

        $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $complaint = Complaint::whereHas('order', function ($q) use ($user, $userRole) {
            $q->where('store_id', $user->store_id);
            if ($userRole === 'manager' && $user->branch_id) {
                $q->where('branch_id', $user->branch_id);
            }
        })->find($id);

        if (!$complaint) {
            return $this->error('Shikoyat topilmadi.', [], 404);
        }

        $complaint->update([
            'reply' => $request->reply,
            'status' => 'owner_replied',
        ]);

        return $this->success($complaint, 'Javob muvaffaqiyatli saqlandi.');
    }

    /**
     * Customer escalates complaint to SuperAdmin.
     */
    public function escalate(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $complaint = Complaint::where('user_id', $user->id)->find($id);

        if (!$complaint) {
            return $this->error('Shikoyat topilmadi.', [], 404);
        }

        if ($complaint->status !== 'owner_replied') {
            return $this->error('Ushbu shikoyatni moderatorga yuborib bo\'lmaydi.', [], 400);
        }

        $complaint->update([
            'status' => 'in_review',
        ]);

        return $this->success($complaint, 'Shikoyat ko\'rib chiqish uchun SuperAdmin paneliga yuborildi.');
    }
}
