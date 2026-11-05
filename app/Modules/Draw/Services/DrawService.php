<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Verifiable draw using the commit-reveal scheme.
 *
 * Flow:
 *
 *   1. COMMIT - before the draw
 *      The pot, the target slots and the rule are fixed as canonical JSON.
 *      The server creates a secret seed and publishes only
 *      commitment = SHA256(payload | serverSeed).
 *      This makes list and rule immutable without revealing the seed.
 *
 *   2. REVEAL - after the draw
 *      The randomness comes from a public source that did not exist at commit
 *      time (drand round, lottery numbers, Bitcoin block hash).
 *      finalSeed = HMAC-SHA256(beacon, serverSeed), then Fisher-Yates.
 *      The serverSeed is released and anyone can recompute everything.
 *
 * Why both are needed:
 *   - Without the commit, the pot could be reordered once the beacon is known,
 *     steering the result.
 *   - Without the beacon, the operator knows the seed in advance and could keep
 *     creating new draws until one suits them.
 *
 * This class is deliberately free of framework dependencies, so it can also be
 * used in a verification script outside the application.
 *
 * IMPORTANT for the payload: integers and strings only, NO floats. PHP and
 * Python do not guarantee identical float formatting, and a single deviating
 * byte invalidates every commitment check. Rating values therefore do not
 * belong in it; the lots are uids.
 *
 * Every byte produced here is part of the published protocol and is pinned by
 * golden vectors captured from the previous implementation.
 */
final class DrawService
{
    /**
     * Canonical serialisation of the payload.
     *
     * It is part of the protocol: anyone recomputing a commitment must be able
     * to produce exactly the same bytes. Hence no pretty-print options and no
     * escapes that change between PHP versions.
     *
     * Python counterpart: json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
     */
    public function canonical(array $payload): string
    {
        // JSON_THROW_ON_ERROR does not change the bytes; the old code returned false on invalid UTF-8.
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Whether a stored payload string is byte-for-byte what canonical() would
     * produce for its decoded form. Anything that decodes but re-encodes
     * differently (whitespace, escapes, empty object vs. list) has drifted and
     * can no longer be verified against its commitment.
     */
    public function isCanonical(string $stored): bool
    {
        $decoded = json_decode($stored, true);

        if (! is_array($decoded)) {
            return false;
        }

        try {
            return $this->canonical($decoded) === $stored;
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * Creates a cryptographically secure server seed (64 hex characters).
     *
     * random_bytes() is correct here; only the draw itself must be purely
     * deterministic.
     */
    public function generateServerSeed(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * commitment = SHA256(canonical(payload) | serverSeed)
     */
    public function commit(array $payload, string $serverSeedHex): string
    {
        $this->assertHex($serverSeedHex, 'serverSeed');

        return hash('sha256', $this->canonical($payload).'|'.$serverSeedHex);
    }

    /**
     * Checks a published commitment against payload and seed.
     */
    public function verifyCommitment(array $payload, string $serverSeedHex, string $commitment): bool
    {
        return hash_equals($commitment, $this->commit($payload, $serverSeedHex));
    }

    /**
     * finalSeed = HMAC-SHA256(beaconValue, serverSeed)
     *
     * The beacon is the message, the server seed is the key (the 64-char hex
     * string itself, not its bytes). Neither side alone determines the result.
     */
    public function finalSeed(string $serverSeedHex, string $beaconValue): string
    {
        $this->assertHex($serverSeedHex, 'serverSeed');

        if ($beaconValue === '') {
            throw new InvalidArgumentException('beaconValue must not be empty');
        }

        return hash_hmac('sha256', $beaconValue, $serverSeedHex);
    }

    /**
     * Deterministic Fisher-Yates shuffle.
     *
     * Deliberately WITHOUT shuffle(), mt_rand() or random_int(): they are not
     * reproducible, which would make the whole protocol worthless.
     *
     * The index for each round comes from SHA256(seed | i). The first 8 bytes
     * are used as a 63-bit integer (the top bit is masked because PHP integers
     * are signed). The modulo bias is of the order n / 2^63 and irrelevant for
     * pot sizes in the two-digit range, so GMP is not needed.
     *
     * Reference implementation for verifiers: tools/verify_draw.py
     */
    public function shuffle(array $items, string $seedHex): array
    {
        $this->assertHex($seedHex, 'seed');

        $a = array_values($items);

        for ($i = count($a) - 1; $i > 0; $i--) {
            $raw = hash('sha256', $seedHex.'|'.$i, true);
            $x = unpack('J', substr($raw, 0, 8))[1] & 0x7FFFFFFFFFFFFFFF;
            $j = $x % ($i + 1);

            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }

        return $a;
    }

    /**
     * Runs the draw and maps the shuffled pot onto the target slots.
     *
     * $payload must contain:
     *   entries : list of lots (uids or tpids, NO real names)
     *   slots   : list of target positions, e.g. ["A2","B2","C2","A3","B3","C3"]
     *   rule    : plain-text description that was published in advance
     *
     * Slots are filled in the given order. For the group draw that means
     * serpentine: all positions 2 first, then all positions 3, so the first
     * two drawn lots do not end up together in one group.
     *
     * @return array{final_seed: string, shuffled: list<mixed>, assignment: array<int|string, mixed>}
     */
    public function execute(array $payload, string $serverSeedHex, string $beaconValue): array
    {
        foreach (['entries', 'slots', 'rule'] as $key) {
            if (! isset($payload[$key])) {
                throw new InvalidArgumentException(sprintf('payload key "%s" is missing', $key));
            }
        }

        $entries = array_values($payload['entries']);
        $slots = array_values($payload['slots']);

        if (count($entries) != count($slots)) {
            throw new RuntimeException(sprintf('entries (%u) and slots (%u) must have the same length', count($entries), count($slots)));
        }

        if (count($entries) != count(array_unique($entries, SORT_REGULAR))) {
            throw new RuntimeException('entries must be unique');
        }

        $seed = $this->finalSeed($serverSeedHex, $beaconValue);
        $shuffled = $this->shuffle($entries, $seed);

        $assignment = [];

        foreach ($slots as $index => $slot) {
            $assignment[$slot] = $shuffled[$index];
        }

        return ['final_seed' => $seed, 'shuffled' => $shuffled, 'assignment' => $assignment];
    }

    /**
     * Helper for admin input: one entry per line.
     * Blank lines and comments (#) are discarded.
     *
     * @return list<string>
     */
    public function parseLines(string $text): array
    {
        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));

            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    private function assertHex(string $value, string $label): void
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $value)) {
            throw new InvalidArgumentException(sprintf('%s must be 64 lowercase hex chars', $label));
        }
    }
}
