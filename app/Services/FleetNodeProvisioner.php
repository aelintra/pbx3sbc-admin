<?php

namespace App\Services;

use App\Models\Dispatcher;
use App\Models\DrGateway;
use App\Models\Fail2banWhitelist;
use Illuminate\Support\Facades\Log;

/**
 * S10.5 residue — register/update a fleet node as OpenSIPS dispatcher set + Asterisk Peer.
 * Fleet-owned rows carry attrs fleet=node;instance=… (Rule 13 namespace).
 */
class FleetNodeProvisioner
{
    /**
     * Normalize to sip:IP:port (lowercase). Accepts sip:… or host[:port].
     * Fleet Asterisk backends must be literal IPs (not DNS names).
     */
    public static function normalizeSipUri(string $uri): string
    {
        $s = trim($uri);
        if ($s === '') {
            throw new \InvalidArgumentException('backend_uri required');
        }
        if (! str_starts_with(strtolower($s), 'sip:')) {
            $s = 'sip:'.$s;
        }
        $hostPort = strtolower(substr($s, 4));
        if ($hostPort === '') {
            throw new \InvalidArgumentException('backend_uri must look like sip:IP:port');
        }
        if (! str_contains($hostPort, ':') && ! str_starts_with($hostPort, '[')) {
            $hostPort .= ':5060';
        } elseif (str_starts_with($hostPort, '[') && ! str_contains($hostPort, ']:')) {
            $hostPort .= ':5060';
        }
        $normalized = 'sip:'.$hostPort;
        if (! \App\Support\SipIpUri::isValid($normalized)) {
            throw new \InvalidArgumentException(\App\Support\SipIpUri::MESSAGE);
        }

        return $normalized;
    }

    public static function nextSetid(): int
    {
        $max = (int) (Dispatcher::query()->max('setid') ?? 0);

        return max(1, $max + 1);
    }

    public static function nextGwid(): string
    {
        $max = (int) (DrGateway::query()
            ->selectRaw('MAX(CAST(gwid AS UNSIGNED)) as m')
            ->value('m') ?? 0);

        return (string) ($max + 1);
    }

    public static function fleetDispatcherAttrs(string $instanceId, ?string $sourceIp = null): string
    {
        $pairs = [
            'fleet' => 'node',
            'instance' => $instanceId,
        ];
        if ($sourceIp !== null && trim($sourceIp) !== '') {
            $pairs['source_ip'] = trim($sourceIp);
        }

        return DrGateway::formatAttrs($pairs);
    }

    public static function fleetPeerAttrs(string $instanceId, int $setid): string
    {
        return DrGateway::formatAttrs([
            'fleet' => 'node',
            'role' => DrGateway::ROLE_ASTERISK,
            'setid' => (string) $setid,
            'instance' => $instanceId,
        ]);
    }

    /**
     * Host IP from a normalized sip:IP[:port] URI (fleet backends are literal IPs).
     */
    public static function hostIpFromSipUri(string $uri): ?string
    {
        $uri = strtolower(trim($uri));
        if (! str_starts_with($uri, 'sip:')) {
            return null;
        }
        $rest = substr($uri, 4);
        if ($rest === '') {
            return null;
        }
        if (str_starts_with($rest, '[')) {
            if (preg_match('/^\[([^\]]+)\](?::\d+)?$/', $rest, $matches)) {
                return $matches[1];
            }

            return null;
        }
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})(?::\d+)?$/', $rest, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function whitelistCidrForIp(string $ip): string
    {
        if (str_contains($ip, '/')) {
            return $ip;
        }
        if (str_contains($ip, ':')) {
            return $ip;
        }

        return "{$ip}/32";
    }

    public static function fleetHomeWhitelistComment(string $instanceId): string
    {
        return 'Fleet home '.trim($instanceId);
    }

    /**
     * Remove fleet-home Fail2ban whitelist rows for an instance (#5e decom / IP change).
     * Manual site NAT rows (other comments) are untouched.
     *
     * @return array{
     *   ok: bool,
     *   removed: int,
     *   keep_cidr: string|null,
     *   sync_ok: bool,
     *   errors: list<string>
     * }
     */
    public static function retireFail2banWhitelistForInstance(string $instanceId, ?string $keepCidr = null): array
    {
        $instanceId = trim($instanceId);
        if ($instanceId === '') {
            return [
                'ok' => false,
                'removed' => 0,
                'keep_cidr' => $keepCidr,
                'sync_ok' => false,
                'errors' => ['instance_id required'],
            ];
        }

        $comment = self::fleetHomeWhitelistComment($instanceId);
        $query = Fail2banWhitelist::query()->where('comment', $comment);
        if ($keepCidr !== null && trim($keepCidr) !== '') {
            $query->where('ip_or_cidr', '!=', trim($keepCidr));
        }

        $removed = 0;
        foreach ($query->get() as $row) {
            $row->delete();
            $removed++;
        }

        $errors = [];
        $syncOk = true;
        if ($removed > 0) {
            $syncOk = app(WhitelistSyncService::class)->sync();
            if (! $syncOk) {
                $errors[] = 'whitelist rows removed but Fail2ban sync failed';
            }
        }

        return [
            'ok' => $errors === [],
            'removed' => $removed,
            'keep_cidr' => $keepCidr,
            'sync_ok' => $syncOk,
            'errors' => $errors,
        ];
    }

    /**
     * Upsert fleet home IP in Fail2ban whitelist DB, unban if needed, sync jail ignoreip.
     *
     * @return array{
     *   whitelisted: bool,
     *   ip_or_cidr: string|null,
     *   sync_ok: bool,
     *   errors: list<string>
     * }
     */
    public static function syncFail2banWhitelistForBackend(
        string $instanceId,
        string $backendUri,
        ?string $sourceIp = null
    ): array {
        $errors = [];
        $ip = ($sourceIp !== null && trim($sourceIp) !== '')
            ? trim($sourceIp)
            : self::hostIpFromSipUri($backendUri);
        if ($ip === null || $ip === '') {
            return [
                'whitelisted' => false,
                'ip_or_cidr' => null,
                'sync_ok' => false,
                'errors' => ['no host IP for Fail2ban whitelist'],
            ];
        }

        $cidr = self::whitelistCidrForIp($ip);
        $comment = self::fleetHomeWhitelistComment($instanceId);

        try {
            self::retireFail2banWhitelistForInstance($instanceId, $cidr);

            Fail2banWhitelist::query()->updateOrCreate(
                ['ip_or_cidr' => $cidr],
                ['comment' => $comment, 'created_by' => null]
            );

            app(Fail2banService::class)->unbanIP($ip);

            $syncOk = app(WhitelistSyncService::class)->sync();
            if (! $syncOk) {
                $errors[] = 'whitelist DB updated but Fail2ban sync failed';
            }

            return [
                'whitelisted' => true,
                'ip_or_cidr' => $cidr,
                'sync_ok' => $syncOk,
                'errors' => $errors,
            ];
        } catch (\Throwable $e) {
            Log::warning('Fleet home Fail2ban whitelist failed', [
                'instance' => $instanceId,
                'ip' => $cidr,
                'error' => $e->getMessage(),
            ]);

            return [
                'whitelisted' => false,
                'ip_or_cidr' => $cidr,
                'sync_ok' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * S10.5 residue — register/update a fleet node as OpenSIPS dispatcher set + Asterisk Peer.
     * Fleet-owned rows carry attrs fleet=node;instance=… (Rule 13 namespace).
     *
     * @return array{
     *   ok: bool,
     *   created: bool,
     *   updated: bool,
     *   setid: int,
     *   destination: string,
     *   gwid: string|null,
     *   peer_created: bool,
     *   peer_updated: bool,
     *   dry_run: bool,
     *   errors: list<string>,
     *   message?: string,
     *   fail2ban_whitelist?: array<string, mixed>
     * }
     */
    public static function provision(
        string $instanceId,
        string $backendUri,
        ?int $setid = null,
        bool $confirmUpdate = false,
        ?string $description = null,
        ?string $sourceIp = null,
        bool $dryRun = false
    ): array {
        $instanceId = trim($instanceId);
        if ($instanceId === '') {
            return self::fail(['instance_id required'], $dryRun);
        }

        try {
            $uri = self::normalizeSipUri($backendUri);
        } catch (\InvalidArgumentException $e) {
            return self::fail([$e->getMessage()], $dryRun);
        }

        $isUpdate = $setid !== null && $setid >= 1;
        if ($isUpdate && ! $confirmUpdate) {
            return self::fail([
                'Instance already has sbc_dispatcher_setid; pass confirm:true to update edge URI',
            ], $dryRun, $setid, $uri);
        }

        if (! $isUpdate) {
            $setid = self::nextSetid();
        }

        $desc = ($description !== null && trim($description) !== '')
            ? trim($description)
            : "fleet-node {$instanceId}";

        $existingPeer = self::findAsteriskPeer($setid, $instanceId);
        $destCount = Dispatcher::query()->where('setid', $setid)->count();

        if ($dryRun) {
            return [
                'ok' => true,
                'created' => ! $isUpdate || $destCount < 1,
                'updated' => $isUpdate && $destCount >= 1,
                'setid' => $setid,
                'destination' => $uri,
                'gwid' => $existingPeer !== null ? (string) $existingPeer->gwid : null,
                'peer_created' => $existingPeer === null,
                'peer_updated' => $existingPeer !== null,
                'dry_run' => true,
                'errors' => [],
            ];
        }

        $created = false;
        $updated = false;
        $attrs = self::fleetDispatcherAttrs($instanceId, $sourceIp);

        if ($destCount < 1) {
            Dispatcher::query()->create([
                'setid' => $setid,
                'destination' => $uri,
                'socket' => null,
                'state' => 0,
                'probe_mode' => 0,
                'weight' => 1,
                'priority' => 0,
                'attrs' => $attrs,
                'description' => $desc,
            ]);
            $created = true;
        } else {
            $rows = Dispatcher::query()->where('setid', $setid)->get();
            foreach ($rows as $row) {
                $parsed = DrGateway::parseAttrs($row->attrs);
                $fleetNode = ($parsed['fleet'] ?? '') === 'node';
                $sameInstance = ($parsed['instance'] ?? '') === $instanceId || ($parsed['instance'] ?? '') === '';
                if (($fleetNode && $sameInstance) || $rows->count() === 1) {
                    $row->destination = $uri;
                    $row->attrs = $attrs;
                    $row->description = $desc;
                    $row->save();
                    $updated = true;
                }
            }
            if (! $updated) {
                return self::fail([
                    "setid {$setid} has destinations not owned by fleet instance {$instanceId}",
                ], false, $setid, $uri);
            }
        }

        $peerCreated = false;
        $peerUpdated = false;
        $gwid = null;
        $peerAttrs = self::fleetPeerAttrs($instanceId, $setid);

        if ($existingPeer === null) {
            $gwid = self::nextGwid();
            $peer = new DrGateway([
                'gwid' => $gwid,
                'type' => 0,
                'address' => $uri,
                'strip' => 0,
                'pri_prefix' => null,
                'attrs' => $peerAttrs,
                'probe_mode' => 0,
                'state' => 0,
                'socket' => null,
                'description' => $desc,
            ]);
            $peer->save();
            $peerCreated = true;
        } else {
            $existingPeer->address = $uri;
            $existingPeer->attrs = $peerAttrs;
            $existingPeer->description = $desc;
            $existingPeer->save();
            $gwid = (string) $existingPeer->gwid;
            $peerUpdated = true;
        }

        $fail2banWhitelist = self::syncFail2banWhitelistForBackend($instanceId, $uri, $sourceIp);
        if (! empty($fail2banWhitelist['errors'])) {
            Log::warning('Provision edge: Fail2ban whitelist incomplete', [
                'instance' => $instanceId,
                'errors' => $fail2banWhitelist['errors'],
            ]);
        }

        return [
            'ok' => true,
            'created' => $created,
            'updated' => $updated || $peerUpdated,
            'setid' => $setid,
            'destination' => $uri,
            'gwid' => $gwid,
            'peer_created' => $peerCreated,
            'peer_updated' => $peerUpdated,
            'dry_run' => false,
            'errors' => [],
            'fail2ban_whitelist' => $fail2banWhitelist,
        ];
    }

    public static function findAsteriskPeer(int $setid, string $instanceId): ?DrGateway
    {
        return DrGateway::query()->get()->first(function (DrGateway $gw) use ($setid, $instanceId) {
            $parsed = DrGateway::parseAttrs($gw->attrs);
            $role = $gw->peerRole();
            if ($role !== DrGateway::ROLE_ASTERISK && ($parsed['role'] ?? '') !== DrGateway::ROLE_ASTERISK) {
                return false;
            }
            if (! isset($parsed['setid']) || (int) $parsed['setid'] !== $setid) {
                return false;
            }
            $inst = (string) ($parsed['instance'] ?? '');

            return $inst === '' || $inst === $instanceId;
        });
    }

    /**
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private static function fail(array $errors, bool $dryRun, ?int $setid = null, ?string $uri = null): array
    {
        return [
            'ok' => false,
            'created' => false,
            'updated' => false,
            'setid' => $setid ?? 0,
            'destination' => $uri ?? '',
            'gwid' => null,
            'peer_created' => false,
            'peer_updated' => false,
            'dry_run' => $dryRun,
            'errors' => $errors,
            'message' => $errors[0] ?? 'provision failed',
        ];
    }
}
