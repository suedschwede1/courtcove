#!/usr/bin/env bash
# booking-guard: blocks confidential information before it leaves the machine.
#
#   guard.sh push <remote> <url>   (pre-push; reads "<local ref> <local sha> <remote ref> <remote sha>" lines on stdin)
#   guard.sh staged                (pre-commit; checks the staged changes)
#   guard.sh message <file>        (commit-msg; checks the commit message)
#
# The term list is NOT part of the repository. It is a local file, one extended regular expression per line
# ('#' comments and blank lines are ignored): $BOOKING_GUARD_TERMS_FILE, default ~/.config/booking-guard/terms.txt.
# Without that file (or with an empty one) every check fails closed. Findings are reported as "<file>:<line>" plus
# the NUMBER of the list entry; the matched text is never printed. Setup and rules: docs/security-runbook.md.
#
# Generic rules (no list needed): credential assignments, private key headers, secret-like file names
# (.env, *.sql, *.pem, *.key, *.zip, ...), IPv4 addresses outside documentation/loopback/private ranges.
# A line containing the marker "guard:allow" is exempt from the generic rules (never from the term list).
# Optional: gitleaks, when installed (skip with BOOKING_GUARD_NO_GITLEAKS=1).
set -u

ZERO=0000000000000000000000000000000000000000
TERMS_FILE="${BOOKING_GUARD_TERMS_FILE:-$HOME/.config/booking-guard/terms.txt}"
SELF="${BASH_SOURCE[0]}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
CTX=""
FOUND=0
TERMS=()
TERMS_ALL=""

say() { printf '%s\n' "$*" >&2; }

load_terms() {
    if [ ! -f "$TERMS_FILE" ]; then
        say "booking-guard: the local term list is missing, nothing was checked, refusing."
        say "  Create it (outside the repository): one extended regex per line, '#' for comments."
        say "  Default location: ~/.config/booking-guard/terms.txt, or point BOOKING_GUARD_TERMS_FILE to it."
        say "  See docs/security-runbook.md. Do not bypass this check with --no-verify."
        return 1
    fi
    TERMS=()
    while IFS= read -r line; do
        TERMS+=("$line")
    done < <(grep -v '^[[:space:]]*\(#\|$\)' "$TERMS_FILE")
    if [ "${#TERMS[@]}" -eq 0 ]; then
        say "booking-guard: the local term list is empty, nothing was checked, refusing."
        return 1
    fi
    TERMS_ALL=""
    local t
    for t in "${TERMS[@]}"; do TERMS_ALL="${TERMS_ALL:+$TERMS_ALL|}($t)"; done
    return 0
}

hit() { # <meta> <reason>
    say "  ${CTX}${1}  [${2}]"
    FOUND=1
}

# Generic credential assignment: (1) name = quoted literal of 12+ token characters, or (2) env-file style
# NAME_WITH_PASSWORD=unquoted literal (8+). Placeholders are excluded below; tests/ is exempt (dummy fixtures).
CRED_RE="((password|passwd|secret|token|api[_-]?key|app_key|private[_-]?key|access[_-]?key)[A-Za-z0-9_]*['\"]?[[:space:]]*(=>|=|:)[[:space:]]*['\"][A-Za-z0-9+/_.:=-]{12,}['\"])|(^[[:space:]]*[A-Z0-9_]*(PASSWORD|SECRET|TOKEN|API_KEY|APP_KEY|PRIVATE_KEY|ACCESS_KEY)[A-Z0-9_]*=[^[:space:]\$\"'<{(]{8,})"
PLACEHOLDER_RE="redacted|example|placeholder|changeme|dummy|fake|guard:allow"
KEY_RE="-----BEGIN [A-Z ]*PRIVATE KEY-----"

# scan_files <meta> <text>: line N of <text> is described by line N of <meta> (e.g. "path:12").
scan_files() {
    local meta=$1 text=$2 n re ln
    if grep -qiE -e "$TERMS_ALL" "$text"; then
        n=0
        for re in "${TERMS[@]}"; do
            n=$((n + 1))
            while IFS= read -r ln; do
                hit "$(sed -n "${ln}p" "$meta")" "term list entry #$n"
            done < <(grep -niE -e "$re" "$text" | cut -d: -f1 | head -20)
        done
    fi
    # credentials
    while IFS= read -r ln; do
        local cm
        cm="$(sed -n "${ln}p" "$meta")"
        case "$cm" in tests/*) continue ;; esac
        hit "$cm" "credential-like assignment"
    done < <(grep -niE -e "$CRED_RE" "$text" | grep -viE -e "$PLACEHOLDER_RE" | cut -d: -f1 | head -20)
    # private key header
    while IFS= read -r ln; do
        hit "$(sed -n "${ln}p" "$meta")" "private key header"
    done < <(grep -nE -e "$KEY_RE" "$text" | cut -d: -f1 | head -5)
    # IPv4 outside documentation, loopback and private ranges
    while IFS= read -r ln; do
        local m
        m="$(sed -n "${ln}p" "$meta")"
        case "$m" in *composer.lock:* | *package-lock.json:* | *.lock:*) continue ;; esac
        sed -n "${ln}p" "$text" | grep -qi 'guard:allow' && continue
        hit "$m" "public IPv4 address"
    done < <(grep -noE '([0-9]{1,3}\.){3}[0-9]{1,3}' "$text" | awk -F: '
        { ip=$2; split(ip,o,"."); ok=1; for(i=1;i<=4;i++) if (o[i]+0>255) ok=0; if(!ok) next
          if (o[1]==o[2] && o[2]==o[3] && o[3]==o[4]) next
          if (ip ~ /^(127|10|0|255)\./ || ip ~ /^192\.168\./ || ip ~ /^203\.0\.113\./ || ip ~ /^198\.51\.100\./ || ip ~ /^192\.0\.2\./) next
          if (ip ~ /^172\.(1[6-9]|2[0-9]|3[01])\./) next
          print $1 }' | uniq | head -20)
}

# scan_names: file names, one per line on stdin.
scan_names() {
    local p
    while IFS= read -r p; do
        [ -n "$p" ] || continue
        printf 'file-name:%s\n' "$p" > "$TMP/n.meta"
        printf '%s\n' "$p" > "$TMP/n.text"
        # term list against the name (the name itself is not printed)
        if grep -qiE -e "$TERMS_ALL" "$TMP/n.text"; then
            local n=0 re
            for re in "${TERMS[@]}"; do
                n=$((n + 1))
                grep -qiE -e "$re" "$TMP/n.text" && hit "a file name in this change (withheld)" "term list entry #$n"
            done
        fi
        case "$p" in
            .env.example | .env.dusk.example | */.env.example) ;;
            .env | .env.* | */.env | */.env.*) hit "$p" "secret-like file name" ;;
            tests/Fixtures/*.sql) ;;
            *.sql | *.sql.gz | *.pem | *.key | *.zip | *.p12 | *.pfx | *.sqlite | *.sqlite3 | *id_rsa* | *.kdbx | *.htpasswd)
                hit "$p" "secret-like file name" ;;
        esac
    done
}

# diff_to_files: unified diff (-U0) on stdin -> $TMP/d.meta ("path:line") and $TMP/d.text (added line) for added lines.
diff_to_files() {
    awk -v meta="$TMP/d.meta" -v text="$TMP/d.text" '
        /^\+\+\+ / { f = substr($0, 7); if ($0 == "+++ /dev/null") f = ""; next }
        /^@@/ { s = $0; sub(/^@@ -[0-9,]+ \+/, "", s); sub(/[ ,].*$/, "", s); n = s + 0; next }
        /^\+/ && f != "" { print f ":" n > meta; print substr($0, 2) > text; n++ }
    '
    [ -f "$TMP/d.meta" ] || : > "$TMP/d.meta"
    [ -f "$TMP/d.text" ] || : > "$TMP/d.text"
}

scan_text_block() { # <label> <text on stdin>: label:line
    local label=$1
    cat > "$TMP/t.text"
    [ -s "$TMP/t.text" ] || return 0
    awk -v l="$label" '{ print l ":" NR }' "$TMP/t.text" > "$TMP/t.meta"
    scan_files "$TMP/t.meta" "$TMP/t.text"
}

check_commit() { # <sha> <ref label>
    local c=$1 short
    short=$(git rev-parse --short "$c")
    CTX="commit $short ($2): "
    scan_text_block "message" < <(git log -1 --format=%B "$c")
    scan_text_block "identity" < <(git log -1 --format='%an%n%ae%n%cn%n%ce' "$c")
    if [ "$(git rev-list --parents -n1 "$c" | wc -w)" -le 2 ]; then
        scan_names < <(git -c core.quotepath=off diff-tree -r --no-commit-id --name-only --diff-filter=ACMRT --root "$c")
        rm -f "$TMP/d.meta" "$TMP/d.text"
        git -c core.quotepath=off show --format= -U0 --no-color --no-ext-diff "$c" | diff_to_files
        scan_files "$TMP/d.meta" "$TMP/d.text"
    fi
    CTX=""
}

run_gitleaks() { # <log-opts or "--staged">
    [ "${BOOKING_GUARD_NO_GITLEAKS:-}" = 1 ] && return 0
    command -v gitleaks > /dev/null 2>&1 || return 0
    if [ "$1" = "--staged" ]; then
        gitleaks git --staged --no-banner --redact --exit-code 99 --log-level error > "$TMP/gl.out" 2>&1
    else
        gitleaks git --log-opts="$1" --no-banner --redact --exit-code 99 --log-level error > "$TMP/gl.out" 2>&1
    fi
    local rc=$?
    if [ "$rc" -eq 99 ]; then
        say "booking-guard: gitleaks reported findings (values redacted):"
        grep -E '^(File|Line|RuleID|Commit):' "$TMP/gl.out" | paste -sd' ' - | sed 's/ File:/\n  File:/g' >&2
        FOUND=1
    fi
}

cache_file() {
    local dir key
    dir="$(git rev-parse --git-common-dir)/booking-guard"
    mkdir -p "$dir"
    key=$(cat "$TERMS_FILE" "$SELF" | git hash-object --stdin)
    printf '%s/ok-%s' "$dir" "$key"
}

cmd_push() {
    local refs=() lref lsha rref rsha
    while read -r lref lsha rref rsha; do
        [ -n "${lref:-}" ] || continue
        [ "$lsha" = "$ZERO" ] && continue # deleting a ref: nothing to scan
        refs+=("$lref $lsha ${rsha:-$ZERO}")
    done
    [ "${#refs[@]}" -gt 0 ] || exit 0
    load_terms || exit 1
    if [ "${#refs[@]}" -gt 1 ] && [ "${BOOKING_GUARD_ALLOW_MULTI:-}" != 1 ]; then
        say "booking-guard: this push sends ${#refs[@]} refs at once (--all / --mirror / several branches)."
        say "  Push one branch at a time, or confirm deliberately with BOOKING_GUARD_ALLOW_MULTI=1."
        exit 1
    fi
    local cache seen=0 bad=0 total=0 entry c range
    cache=$(cache_file)
    touch "$cache"
    for entry in "${refs[@]}"; do
        read -r lref lsha rsha <<< "$entry"
        if [ "$rsha" = "$ZERO" ] || ! git cat-file -e "$rsha^{commit}" 2> /dev/null; then
            range="$lsha" # new branch: against an empty base, i.e. the whole history
        else
            range="$rsha..$lsha"
        fi
        for c in $(git rev-list "$range"); do
            total=$((total + 1))
            grep -qx "$c" "$cache" && { seen=$((seen + 1)); continue; }
            FOUND=0
            check_commit "$c" "$lref"
            if [ "$FOUND" -eq 0 ]; then echo "$c" >> "$cache"; else bad=$((bad + 1)); fi
        done
        FOUND=0
        run_gitleaks "$range"
        [ "$FOUND" -eq 0 ] || bad=$((bad + 1))
    done
    say "booking-guard: $total commit(s) in range, $seen already verified, $bad blocked."
    [ "$bad" -eq 0 ] || { say "booking-guard: push refused. Fix the findings (history rewrite for committed ones); do not use --no-verify."; exit 1; }
    exit 0
}

cmd_staged() {
    load_terms || exit 1
    CTX="staged: "
    scan_names < <(git -c core.quotepath=off diff --cached --name-only --diff-filter=ACMRT)
    git -c core.quotepath=off diff --cached -U0 --no-color --no-ext-diff | diff_to_files
    scan_files "$TMP/d.meta" "$TMP/d.text"
    run_gitleaks "--staged"
    [ "$FOUND" -eq 0 ] || { say "booking-guard: commit refused. Remove the findings; do not use --no-verify."; exit 1; }
    exit 0
}

cmd_message() {
    load_terms || exit 1
    CTX="commit message: "
    scan_text_block "message" < <(grep -v '^#' "$1")
    [ "$FOUND" -eq 0 ] || { say "booking-guard: commit message refused. Reword it; do not use --no-verify."; exit 1; }
    exit 0
}

case "${1:-}" in
    push) shift; cmd_push "$@" ;;
    staged) cmd_staged ;;
    message) cmd_message "${2:?commit message file}" ;;
    *) say "usage: guard.sh push|staged|message <file>"; exit 2 ;;
esac
