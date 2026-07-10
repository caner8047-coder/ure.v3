<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PlanningService;

class ProductionPlanningController extends Controller
{
    protected PlanningService $planningService;

    public function __construct(PlanningService $planningService)
    {
        $this->planningService = $planningService;
    }

    public function getPersonnelList()
    {
        $result = $this->planningService->getPersonnelList();

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function getPersonnelTasks(Request $request, $personelNo)
    {
        $result = $this->planningService->getPersonnelTasks(intval($personelNo));

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function incrementTask($taskId)
    {
        $result = $this->planningService->incrementTask(intval($taskId));

        return response()->json($result);
    }

    public function decrementTask($taskId)
    {
        $result = $this->planningService->decrementTask(intval($taskId));

        return response()->json($result);
    }

    public function dependencyInfo(Request $request, $id)
    {
        $result = $this->planningService->dependencyInfo(intval($id));

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);
        }

        return response()->json(array_merge(['success' => true], $result));
    }

    public function notifyDependency(Request $request, $id)
    {
        $componentNo = intval($request->input('component_no', 0));
        $supplierTaskNo = intval($request->input('supplier_task_no', 0));
        $user = $request->user();

        $result = $this->planningService->notifyDependency(intval($id), $componentNo, $supplierTaskNo, $user);

        if (isset($result['success']) && !$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        return response()->json($result);
    }

    public function deleteTask($taskId)
    {
        $result = $this->planningService->deleteTask(intval($taskId));

        if (isset($result['success']) && !$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        return response()->json($result);
    }

    public function updateTaskDate(Request $request, $taskId)
    {
        $newDate = $request->input('date');
        if (!$newDate) {
            return response()->json(['success' => false, 'message' => 'Tarih gerekli.']);
        }

        $result = $this->planningService->updateTaskDate(intval($taskId), $newDate);

        if (isset($result['success']) && !$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        return response()->json($result);
    }

    public function getTransferOptions($taskId)
    {
        $result = $this->planningService->getTransferOptions(intval($taskId));

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);
        }

        return response()->json(array_merge(['success' => true], $result));
    }

    public function transferTask(Request $request, $taskId)
    {
        $targetPersonnelNo = intval($request->input('target_personnel_no', 0));
        if ($targetPersonnelNo <= 0) {
            return response()->json(['success' => false, 'message' => 'Hedef personel seçilmedi.'], 422);
        }

        $result = $this->planningService->transferTask(intval($taskId), $targetPersonnelNo);

        if (isset($result['success']) && !$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        return response()->json($result);
    }

    public function getPoolTasks($departmentId)
    {
        $result = $this->planningService->getPoolTasks(intval($departmentId));

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function getDepartmentPersonnelTasks($departmentId)
    {
        $result = $this->planningService->getDepartmentPersonnelTasks(intval($departmentId));

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function assignFromPool(Request $request)
    {
        $poolId = intval($request->input('pool_id'));
        $personnelNo = intval($request->input('personnel_no'));
        $targetDate = $request->input('target_date', date('Y-m-d'));

        $result = $this->planningService->assignFromPool($poolId, $personnelNo, $targetDate);

        if (isset($result['success']) && !$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        return response()->json($result);
    }

    public function setTaskQuantity(Request $request, $taskId)
    {
        $targetQuantity = intval($request->input('target_quantity', -1));
        if ($targetQuantity < 0) {
            return response()->json(['success' => false, 'message' => 'Geçerli bir adet girin.'], 422);
        }

        $result = $this->planningService->setTaskQuantity(intval($taskId), $targetQuantity);

        if (isset($result['success']) && !$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        return response()->json($result);
    }

    public function getTaskHistory($taskId)
    {
        $result = $this->planningService->getTaskHistory(intval($taskId));

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);
        }

        return response()->json(array_merge(['success' => true], $result));
    }
}
