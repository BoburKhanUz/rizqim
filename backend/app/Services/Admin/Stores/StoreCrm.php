<?php

namespace App\Services\Admin\Stores;

use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\StoreCrmProfile;
use App\Models\StoreNote;
use App\Models\StoreTask;
use App\Models\User;
use App\Services\Admin\Today\PlatformStats;

/**
 * P4-22: CRM-lite of the Ovqatxonalar section: notes, tasks with a due date and an assignee, a store's next contact
 * date. A note or a task belongs to a store or to an application (exactly one).
 */
class StoreCrm
{
    public function addNote(Store|StoreApplication $about, User $member, string $body): StoreNote
    {
        return StoreNote::create([
            ...$this->owner($about),
            'author_id' => $member->id,
            'body' => trim($body),
        ]);
    }

    public function addTask(Store|StoreApplication $about, User $member, string $title, string $dueOn, ?int $assigneeId): StoreTask
    {
        return StoreTask::create([
            ...$this->owner($about),
            'title' => trim($title),
            'due_on' => $dueOn,
            'assignee_id' => $assigneeId ?? $member->id,
            'created_by' => $member->id,
        ]);
    }

    /**
     * @param  array{title?: string, due_on?: string, assignee_id?: int|null, done?: bool}  $fields
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function updateTask(StoreTask $task, array $fields, User $member): array
    {
        $before = $this->taskState($task);

        if (array_key_exists('done', $fields)) {
            $done = (bool) $fields['done'];
            if ($done && $task->done_at === null) {
                $task->forceFill(['done_at' => PlatformStats::now(), 'done_by' => $member->id]);
            } elseif (!$done) {
                $task->forceFill(['done_at' => null, 'done_by' => null]);
            }
        }
        $task->fill(array_intersect_key($fields, array_flip(['title', 'due_on', 'assignee_id'])));
        $task->save();

        return ['before' => $before, 'after' => $this->taskState($task->refresh())];
    }

    /**
     * @return array{before: string|null, after: string|null}
     */
    public function setNextContact(Store $store, ?string $date, User $member): array
    {
        $profile = StoreCrmProfile::query()->firstOrNew(['store_id' => $store->id]);
        $before = $profile->next_contact_on?->format('Y-m-d');
        $profile->forceFill(['next_contact_on' => $date, 'updated_by' => $member->id])->save();

        return ['before' => $before, 'after' => $date];
    }

    /**
     * @return array{title: string, due_on: string, assignee_id: int|null, done: bool}
     */
    private function taskState(StoreTask $task): array
    {
        return [
            'title' => $task->title,
            'due_on' => $task->due_on->format('Y-m-d'),
            'assignee_id' => $task->assignee_id !== null ? (int) $task->assignee_id : null,
            'done' => $task->done_at !== null,
        ];
    }

    /**
     * @return array{store_id: int|null, application_id: int|null}
     */
    private function owner(Store|StoreApplication $about): array
    {
        return $about instanceof Store
            ? ['store_id' => (int) $about->id, 'application_id' => null]
            : ['store_id' => $about->store_id !== null ? (int) $about->store_id : null, 'application_id' => (int) $about->id];
    }
}
