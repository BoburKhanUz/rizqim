<?php

namespace App\Domain\Realtime\Broadcasting;

use App\Domain\Realtime\ChangeRevision;

/**
 * Wave M2: every broadcast payload carries `rev`, the current change token of each channel it goes to, keyed by the
 * channel name the client subscribed to (`{"pos-branch.12": "4711", "user.5": "90210"}`). A client holding token N for
 * its channel applies an event whose rev is N+1 and re-reads on any other value (it missed something). The token is
 * read when the event is sent (after the commit that caused it), so it is never older than the change it announces.
 */
final class RevisionStamp
{
    /**
     * @param  array<int, mixed>  $channels  channel objects or names (with or without the private-/presence- prefix)
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function apply(array $channels, array $payload): array
    {
        $rev = [];
        foreach ($channels as $channel) {
            $name = preg_replace('/^(private-encrypted-|private-|presence-)/', '', (string) $channel) ?? '';
            $token = self::tokenFor($name);
            if ($token !== null) {
                $rev[$name] = $token;
            }
        }
        if ($rev !== []) {
            $payload['rev'] = $rev;
        }

        return $payload;
    }

    /** The change token a channel's subscribers compare with (null: a channel without a revision scope) */
    public static function tokenFor(string $channel): ?string
    {
        if (preg_match('/^(pos-branch|branch|store|user|courier)\.(\d+)$/', $channel, $m) !== 1) {
            return null;
        }
        $revisions = app(ChangeRevision::class);
        $id = (int) $m[2];

        return match ($m[1]) {
            'pos-branch', 'branch' => $revisions->branch($id),
            'store' => $revisions->store($id),
            default => $revisions->user($id),
        };
    }
}
