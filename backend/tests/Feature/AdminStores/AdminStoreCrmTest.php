<?php

namespace Tests\Feature\AdminStores;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\StoreTask;
use App\Services\Admin\Today\PlatformStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-22: CRM-lite: notes, tasks with due date and assignee, the next contact date; due items in Bugun.
 */
class AdminStoreCrmTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    public function test_notes_tasks_and_next_contact_on_the_card_and_in_bugun(): void
    {
        $store = $this->makeStore();
        $sales = $this->makeMember(PlatformRole::SALES);
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($sales);
        $today = PlatformStats::now()->format('Y-m-d');

        $this->adminJson('POST', "/stores/{$store->id}/notes", $token, ['body' => 'Egasi yangi filial ochmoqchi'])
            ->assertCreated()->assertJsonPath('data.author.id', $sales->id);

        $this->adminJson('POST', '/store-tasks', $token, ['title' => "Payshanba qo'ng'iroq qilish", 'due_on' => PlatformStats::now()->subDay()->format('Y-m-d'), 'store_id' => $store->id])
            ->assertStatus(422)->assertJsonValidationErrors('due_on');
        $task = $this->adminJson('POST', '/store-tasks', $token, ['title' => "Qo'ng'iroq qilish", 'due_on' => $today, 'store_id' => $store->id])
            ->assertCreated()->assertJsonPath('data.state', 'today')->assertJsonPath('data.assignee.id', $sales->id)->json('data');
        $this->adminJson('POST', '/store-tasks', $token, ['title' => 'Kassani tekshirish', 'due_on' => $today, 'store_id' => $store->id, 'assignee_id' => $operator->id])
            ->assertCreated();
        $this->adminJson('POST', '/store-tasks', $token, ['title' => 'Kim?', 'due_on' => $today])
            ->assertStatus(422)->assertJsonValidationErrors(['store_id', 'application_uuid']);

        // An overdue one (the date passed)
        StoreTask::query()->whereKey($task['id'])->update(['due_on' => PlatformStats::now()->subDays(2)->format('Y-m-d')]);

        $this->adminJson('PUT', "/stores/{$store->id}/next-contact", $token, ['next_contact_on' => $today])
            ->assertOk()->assertJsonPath('data.next_contact_on', $today);

        $card = $this->adminJson('GET', "/stores/{$store->id}", $token)->assertOk();
        $this->assertSame($today, $card->json('data.crm.next_contact_on'));
        $this->assertSame('Egasi yangi filial ochmoqchi', $card->json('data.crm.notes.0.body'));
        $this->assertSame(['overdue', 'today'], array_column($card->json('data.crm.tasks'), 'state'));

        $mine = $this->adminJson('GET', '/store-tasks?assignee_id=me', $this->signIn($operator))->assertOk();
        $this->assertSame(['Kassani tekshirish'], array_column($mine->json('data.tasks'), 'title'));
        $counts = $this->adminJson('GET', '/store-tasks', $token)->json('data.counts');
        $this->assertSame(['overdue' => 1, 'today' => 1, 'upcoming' => 0], $counts);

        $groups = collect($this->adminJson('GET', '/today/attention', $this->signIn($operator))->json('data.groups'));
        $due = $groups->firstWhere('kind', 'store_task_due');
        $this->assertSame(3, $due['count']); // two tasks and the contact date
        $this->assertSame('warning', $due['items'][0]['severity']);
        $this->assertSame('/stores/' . $store->id . '?tab=crm', $due['items'][0]['href']);

        // Done: gone from the list and from Bugun
        $this->adminJson('PATCH', '/store-tasks/' . $task['id'], $token, ['done' => true])->assertOk()->assertJsonPath('data.state', 'done');
        $audit = AuditLog::where('action', 'admin.stores.tasks.updated')->sole();
        $this->assertSame(['done' => false], $audit->before);
        $this->assertSame(['done' => true], $audit->after);
        $this->adminJson('PUT', "/stores/{$store->id}/next-contact", $token, ['next_contact_on' => null])->assertOk();
        $groups = collect($this->adminJson('GET', '/today/attention', $this->signIn($operator))->json('data.groups'));
        $this->assertSame(1, $groups->firstWhere('kind', 'store_task_due')['count']);
    }

    public function test_application_notes_show_on_the_application(): void
    {
        $application = $this->makeApplication();
        $token = $this->signIn($this->makeMember(PlatformRole::SALES));
        $this->adminJson('POST', "/store-applications/{$application->uuid}/notes", $token, ['body' => 'Menyuni Excel bilan yubordi'])->assertCreated();
        $this->adminJson('POST', '/store-tasks', $token, ['title' => 'Menyuni kiritish', 'due_on' => PlatformStats::now()->addDay()->format('Y-m-d'), 'application_uuid' => $application->uuid])
            ->assertCreated()->assertJsonPath('data.application.uuid', $application->uuid)->assertJsonPath('data.state', 'upcoming');

        $card = $this->adminJson('GET', "/store-applications/{$application->uuid}", $token)->assertOk();
        $this->assertSame('Menyuni Excel bilan yubordi', $card->json('data.crm.notes.0.body'));
        $this->assertSame('Menyuni kiritish', $card->json('data.crm.tasks.0.title'));

        $viewer = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $this->adminJson('POST', "/store-applications/{$application->uuid}/notes", $viewer, ['body' => 'x'])->assertForbidden();
    }
}
