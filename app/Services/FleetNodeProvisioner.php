<?php

namespace App\Services;

use App\Models\Dispatcher;
use App\Models\DrGateway;

/**
 * S10.5 residue — register/update a fleet node as OpenSIPS dispatcher set + Asterisk Peer.
 * Fleet-owned rows carry attrs fleet=node;instance=… (Rule 13 namespace).
 */
class FleetNodeProvisioner
{
    /**
     * Normalize to sip:host:port (lowercase). Accepts sip:… or host[:port].
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
        $hostPort = trim($hostPort, '[]');
        if ($hostPort === '') {
            throw new \InvalidArgumentException('backend_uri must look like sip:host:port');
        }
        if (! str_contains($hostPort, ':')) {
            $hostPort .= ':5060';
        }
        if (! preg_match('/^[a-z0-9._\-]+:\d+$/', $hostPort)) {
            throw new \InvalidArgumentException('backend_uri must look like sip:host:port');
        }

        return 'sip:'.$hostPort;
    }

    public static function nextSetid(): int
    {
        $max = (int) (Dispatcher::query()->max('setid') ?? 0);

        return max(1, $max + 1);
    }

    public static function nextGwid(): string
    {
        return (string) ((int) (DrGateway::query()->max('gwid') ?? 0) + 1);
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
     *   message?: string
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
