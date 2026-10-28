<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * P4-11: what the current admin request did, for the one audit entry App\Http\Middleware\RecordAdminAudit writes when
 * the request ends (scoped to the request). Every admin write is audited without the controller writing the entry:
 * the action defaults to the route name (admin.team.update, ...), the actor to the signed-in member, and controllers
 * only add what they know: the subject, its fields before and after, the reason, extra details.
 *
 * Sign-in steps are attempts, so they are recorded whatever the answer (recordAlways()), with the account being
 * signed in to as the actor. PII revealed to a member with admin.pii.view (AdminPii) is recorded on reads too.
 */
class AdminAudit
{
    public ?string $action = null;

    public ?Model $subject = null;

    public ?User $actor = null;

    public bool $actorSet = false;

    public ?int $storeId = null;

    /** @var array<string, mixed> */
    public array $before = [];

    /** @var array<string, mixed> */
    public array $after = [];

    /** @var array<string, mixed> */
    public array $meta = [];

    public bool $always = false;

    public bool $skip = false;

    /** @var list<array{type: string, id: int|string|null, field: string}> */
    public array $revealed = [];

    /**
     * The current request's context (kept on the request, so each request starts empty).
     */
    public static function for(?\Illuminate\Http\Request $request = null): self
    {
        $request ??= request();
        $audit = $request->attributes->get(self::class);
        if (!$audit instanceof self) {
            $audit = new self();
            $request->attributes->set(self::class, $audit);
        }

        return $audit;
    }

    public function action(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function subject(?Model $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function actor(?User $actor): static
    {
        $this->actor = $actor;
        $this->actorSet = true;

        return $this;
    }

    public function store(?int $storeId): static
    {
        $this->storeId = $storeId;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function changes(array $before, array $after): static
    {
        $this->before = $before;
        $this->after = $after;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): static
    {
        $this->meta = [...$this->meta, ...$meta];

        return $this;
    }

    /**
     * Record this request whatever its answer (sign-in attempts).
     */
    public function recordAlways(): static
    {
        $this->always = true;

        return $this;
    }

    /**
     * Nothing to record (a write that changed nothing).
     */
    public function skip(): static
    {
        $this->skip = true;

        return $this;
    }

    public function revealed(string $type, int|string|null $id, string $field): void
    {
        $this->revealed[] = ['type' => $type, 'id' => $id, 'field' => $field];
    }
}
