#!/usr/bin/env python3
"""
Unabhaengige Pruefung einer veroeffentlichten Auslosung.

Dieses Skript kommt bewusst ohne den Server aus: Es liest die vom Protokoll
veroeffentlichten Werte und rechnet Commitment und Ziehung selbst nach. Wer
zur Pruefung den Server fragen muss, hat nichts geprueft.

Aufruf:

    python3 tools/verify_draw.py record.json
    curl -s https://example.test/booking/draw/record/<slug>/json > record.json

Erwartete Felder (die JSON-Ansicht des Protokolls liefert sie):

    payload      : {"entries": [...], "slots": [...], "rule": "..."}
    commitment   : 64 Hex
    server_seed  : 64 Hex   (erst nach der Ausfuehrung vorhanden)
    beacon_value : drand randomness
    assignment   : {"A2": 1, ...}
"""

import hashlib
import hmac
import json
import sys


def canonical(payload: dict) -> str:
    """Muss byteweise dem PHP-Pendant entsprechen.

    PHP: json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    """
    return json.dumps(payload, ensure_ascii=False, separators=(",", ":"))


def commit(payload: dict, server_seed: str) -> str:
    return hashlib.sha256(f"{canonical(payload)}|{server_seed}".encode()).hexdigest()


def final_seed(server_seed: str, beacon_value: str) -> str:
    return hmac.new(server_seed.encode(), beacon_value.encode(), hashlib.sha256).hexdigest()


def shuffle(items: list, seed: str) -> list:
    a = list(items)

    for i in range(len(a) - 1, 0, -1):
        raw = hashlib.sha256(f"{seed}|{i}".encode()).digest()
        x = int.from_bytes(raw[:8], "big") & 0x7FFFFFFFFFFFFFFF
        j = x % (i + 1)
        a[i], a[j] = a[j], a[i]

    return a


def main(path: str) -> int:
    with open(path, encoding="utf-8") as fh:
        rec = json.load(fh)

    if "server_seed" not in rec:
        print("Diese Ziehung ist noch nicht ausgefuehrt - der Server-Seed ist")
        print("noch nicht freigegeben. Nachrechnen ist erst danach moeglich.")
        print(f"\nCommitment (festgeschrieben): {rec.get('commitment')}")
        print(f"Zufallsquelle              : {rec.get('beacon_source')}")
        return 0

    ok = True

    expected = commit(rec["payload"], rec["server_seed"])
    match = hmac.compare_digest(expected, rec["commitment"])
    print(f"Commitment  : {'OK' if match else 'ABWEICHUNG'}")
    if not match:
        print(f"  veroeffentlicht : {rec['commitment']}")
        print(f"  nachgerechnet   : {expected}")
        ok = False

    seed = final_seed(rec["server_seed"], rec["beacon_value"])
    order = shuffle(rec["payload"]["entries"], seed)
    result = dict(zip(rec["payload"]["slots"], order))

    match = result == rec["assignment"]
    print(f"Zuteilung   : {'OK' if match else 'ABWEICHUNG'}")
    if not match:
        print(f"  veroeffentlicht : {rec['assignment']}")
        print(f"  nachgerechnet   : {result}")
        ok = False

    print(f"\nFinal seed  : {seed}")
    print("Reihenfolge :", " -> ".join(str(x) for x in order))

    if ok:
        print("\nDie Ziehung ist korrekt und wurde nicht manipuliert.")

    return 0 if ok else 1


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(__doc__)
        sys.exit(2)

    sys.exit(main(sys.argv[1]))
