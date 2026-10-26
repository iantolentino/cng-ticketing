<?php
declare(strict_types=1);

require_once __DIR__ . '/roster.php';
require_once __DIR__ . '/notifications.php';

/** Final Team Leader decision; status, calendar, notification and history commit together. */
function leave_request_decide(int $requestId, string $decision, array $actor): array
{
    if (!app_linked_leave_available()) return leave_request_decide_legacy($requestId, $decision, $actor);
    $role = (string) ($actor['role_slug'] ?? '');
    if (!in_array($role, ['team-leader', 'super-admin'], true) || !in_array($decision, ['approve', 'reject'], true)) {
        return ['ok' => false, 'error' => 'You do not have permission to decide this leave request.'];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $activeRequest = app_schema_has('leave_requests', 'deleted_at', $pdo) ? ' AND deleted_at IS NULL' : '';
        $query = $pdo->prepare('SELECT * FROM leave_requests WHERE id=?' . $activeRequest . ' FOR UPDATE');
        $query->execute([$requestId]);
        $request = $query->fetch();
        if (!$request || !in_array($request['status'], ['pending', 'team_leader_approved'], true)) {
            throw new InvalidArgumentException('This request is no longer waiting for a decision.');
        }
        $eventId = null;
        if ($decision === 'approve') {
            $saved = roster_leave_save([
                'staff_id' => (int) $request['staff_id'], 'leave_request_id' => $requestId,
                'start_date' => $request['start_date'], 'end_date' => $request['end_date'],
                'leave_type' => $request['leave_type'] ?: 'annual',
                'notes' => 'Approved leave request #' . $requestId,
            ], $actor);
            if (!$saved['ok']) throw new InvalidArgumentException($saved['error']);
            $eventId = $saved['id'];
        }
        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare('UPDATE leave_requests SET status=?,team_leader_approval_by=?,team_leader_approval_at=NOW() WHERE id=?')
            ->execute([$status, (int) $actor['id'], $requestId]);
        audit_admin_action((int) $actor['id'], 'leave_' . $decision, 'leave_request', $requestId,
            ['stage' => $role === 'super-admin' ? 'super_admin' : 'team_leader', 'before' => ['status' => $request['status']], 'after' => ['status' => $status], 'calendar_event_id' => $eventId]);
        if ($request['employee_user_id']) {
            notify_user((int) $request['employee_user_id'], (int) $actor['id'], 'approval',
                'Leave request ' . $status, 'Leave from ' . $request['start_date'] . ' to ' . $request['end_date'], 'leave-requests.php');
        }
        $pdo->commit();
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The leave decision could not be saved.'];
    }
}

/** Preserve legacy columns while allowing Team Leaders to finalize leave without a Department Head. */
function leave_request_decide_legacy(int $requestId, string $decision, array $actor): array
{
    $role = $actor['role_slug'] ?? '';
    if (!in_array($role, ['team-leader', 'department-head', 'super-admin'], true) || !in_array($decision, ['approve', 'reject'], true)) return ['ok'=>false, 'error'=>'You do not have permission to approve this request.'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $activeRequest = app_schema_has('leave_requests', 'deleted_at', $pdo) ? ' AND deleted_at IS NULL' : '';
        $query = $pdo->prepare('SELECT * FROM leave_requests WHERE id=?' . $activeRequest . ' FOR UPDATE');
        $query->execute([$requestId]);
        $request = $query->fetch();
        $allowedStatuses = in_array($role, ['team-leader', 'super-admin'], true) ? ['pending', 'team_leader_approved'] : ['team_leader_approved'];
        if (!$request || !in_array($request['status'], $allowedStatuses, true)) throw new InvalidArgumentException('This request is no longer waiting for your decision.');
        // The legacy status enum has no `approved` value; this value is already displayed as Approved.
        $status = $decision === 'reject' ? 'rejected' : 'department_head_approved';
        $eventId = null;
        if ($status === 'department_head_approved') {
            $saved = roster_leave_save(['staff_id'=>(int)$request['staff_id'], 'start_date'=>$request['start_date'], 'end_date'=>$request['end_date'], 'leave_type'=>$request['leave_type'] ?: 'annual', 'notes'=>'Approved leave request #' . $requestId], $actor);
            if (!$saved['ok']) throw new InvalidArgumentException($saved['error']);
            $eventId = $saved['id'];
        }
        $stage = $role === 'team-leader' ? 'team_leader' : 'department_head';
        if ($role === 'super-admin' && $status === 'department_head_approved' && $request['status'] === 'pending') {
            $pdo->prepare('UPDATE leave_requests SET status=?,team_leader_approval_by=?,team_leader_approval_at=NOW(),department_head_approval_by=?,department_head_approval_at=NOW() WHERE id=?')
                ->execute([$status, (int) $actor['id'], (int) $actor['id'], $requestId]);
        } else {
            $pdo->prepare('UPDATE leave_requests SET status=?,' . $stage . '_approval_by=?,' . $stage . '_approval_at=NOW() WHERE id=?')->execute([$status, (int)$actor['id'], $requestId]);
        }
        audit_admin_action((int)$actor['id'], 'leave_' . $decision, 'leave_request', $requestId, ['stage'=>$role === 'super-admin' ? 'super_admin' : $stage, 'before'=>['status'=>$request['status']], 'after'=>['status'=>$status], 'calendar_event_id'=>$eventId]);
        if ($request['employee_user_id']) notify_user((int)$request['employee_user_id'], (int)$actor['id'], 'approval', 'Leave request ' . str_replace('_', ' ', $status), 'Leave from ' . $request['start_date'] . ' to ' . $request['end_date'], 'leave-requests.php');
        if ($status === 'team_leader_approved') notify_many(active_user_ids_by_role('department-head'), (int)$actor['id'], 'approval', 'Leave request needs Department Head approval', 'Leave from ' . $request['start_date'] . ' to ' . $request['end_date'], 'leave-requests.php');
        $pdo->commit();
        return ['ok'=>true, 'error'=>''];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false, 'error'=>$exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The leave decision could not be saved.'];
    }
}

/** Super Admin edit for a request; synchronize its calendar entry in the same transaction. */
function leave_request_admin_update(int $requestId, array $input, array $actor): array
{
    if (($actor['role_slug'] ?? '') !== 'super-admin') return ['ok'=>false, 'error'=>'Super Admin access required.'];
    $start = trim((string) ($input['start_date'] ?? ''));
    $end = trim((string) ($input['end_date'] ?? ''));
    $type = trim((string) ($input['leave_type'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    $email = trim((string) ($input['requester_email'] ?? ''));
    $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
    $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
    if (!$startDate || $startDate->format('Y-m-d') !== $start || !$endDate || $endDate->format('Y-m-d') !== $end || $end < $start) return ['ok'=>false, 'error'=>'Enter a valid leave date range.'];
    if (!isset(ROSTER_LEAVE_TYPES[$type])) return ['ok'=>false, 'error'=>'Choose a valid leave type.'];
    if (mb_strlen($reason) > 5000) return ['ok'=>false, 'error'=>'Keep the reason under 5,000 characters.'];
    if (app_schema_has('leave_requests', 'requester_email') && $email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254)) return ['ok'=>false, 'error'=>'Enter a valid contact email.'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT * FROM leave_requests WHERE id=?' . (app_schema_has('leave_requests', 'deleted_at', $pdo) ? ' AND deleted_at IS NULL' : '') . ' FOR UPDATE');
        $query->execute([$requestId]);
        $request = $query->fetch();
        if (!$request) throw new InvalidArgumentException('Leave request not found.');
        $before = ['start_date'=>$request['start_date'], 'end_date'=>$request['end_date'], 'leave_type'=>$request['leave_type'], 'reason'=>$request['reason'], 'requester_email'=>$request['requester_email'] ?? null];

        $activeEvent = app_schema_has('calendar_events', 'deleted_at', $pdo) ? ' AND deleted_at IS NULL' : '';
        $eventQuery = app_schema_has('calendar_events', 'leave_request_id', $pdo)
            ? $pdo->prepare("SELECT * FROM calendar_events WHERE leave_request_id=? AND event_type='leave'" . $activeEvent . ' FOR UPDATE')
            : $pdo->prepare("SELECT * FROM calendar_events WHERE event_type='leave' AND notes=? AND staff_id <=> ?" . $activeEvent . ' FOR UPDATE');
        if (app_schema_has('calendar_events', 'leave_request_id', $pdo)) $eventQuery->execute([$requestId]);
        else $eventQuery->execute(['Approved leave request #' . $requestId, $request['staff_id']]);
        $event = $eventQuery->fetch() ?: null;
        if ($event) {
            $saved = roster_leave_save(['staff_id'=>(int)$request['staff_id'], 'start_date'=>$start, 'end_date'=>$end, 'leave_type'=>$type, 'notes'=>'Approved leave request #' . $requestId, 'admin_request_edit'=>true], $actor, (int)$event['id']);
            if (!$saved['ok']) throw new InvalidArgumentException($saved['error']);
        }
        $fields = 'start_date=?,end_date=?,leave_type=?,reason=?';
        $values = [$start, $end, $type, $reason];
        if (array_key_exists('requester_email', $request)) { $fields .= ',requester_email=?'; $values[] = $email !== '' ? $email : null; }
        $values[] = $requestId;
        $pdo->prepare('UPDATE leave_requests SET ' . $fields . ' WHERE id=?')->execute($values);
        audit_admin_action((int)$actor['id'], 'leave_request_admin_updated', 'leave_request', $requestId, ['before'=>$before, 'after'=>['start_date'=>$start, 'end_date'=>$end, 'leave_type'=>$type, 'reason'=>$reason, 'requester_email'=>$email ?: null], 'calendar_event_id'=>$event['id'] ?? null]);
        $pdo->commit();
        return ['ok'=>true, 'error'=>''];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false, 'error'=>$exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The leave request could not be updated.'];
    }
}

/** Super Admin removal keeps the request, calendar entry, and attachments in the database. */
function leave_request_admin_soft_delete(int $requestId, array $actor): array
{
    return roster_leave_request_soft_delete($requestId, $actor);
}
