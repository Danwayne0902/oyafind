#!/usr/bin/env bash
#
# Check whether OyaFind's domain can send and receive mail credibly.
#
#   ./scripts/mail-check.sh [domain]
#
# A brand address is only "official" when three things are true:
#   1. mail addressed to it is actually delivered somewhere (MX),
#   2. mail sent from it passes SPF and DKIM,
#   3. the domain publishes a DMARC policy.
# Missing any of these means verification codes from social platforms bounce
# and ordinary mail lands in spam.
#
set -uo pipefail

DOMAIN="${1:-oyafind.com.ng}"
DKIM_SELECTORS="${DKIM_SELECTORS:-zoho zmail brevo mail default s1 s2 k1 google}"

pass() { printf '  \033[32mPASS\033[0m  %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; }
warn() { printf '  \033[33mWARN\033[0m  %s\n' "$1"; }

echo "Mail readiness for ${DOMAIN}"
echo

# --- Receiving ---------------------------------------------------------------
mx="$(dig +short MX "${DOMAIN}" | sort -n)"
if [ -n "${mx}" ]; then
    pass "MX records present"
    echo "${mx}" | sed 's/^/        /'
else
    fail "No MX record — mail to <anything>@${DOMAIN} is bounced"

    # With no MX, senders fall back to the A record. If nothing answers SMTP
    # there, the bounce is guaranteed rather than merely likely.
    a="$(dig +short A "${DOMAIN}" | head -1)"
    if [ -n "${a}" ]; then
        if nc -z -w 5 "${a}" 25 2>/dev/null; then
            warn "No MX, but ${a}:25 accepts SMTP — mail may land somewhere unexpected"
        else
            echo "        (fallback ${a}:25 is closed, so delivery cannot succeed)"
        fi
    fi
fi

# --- Sending: SPF ------------------------------------------------------------
spf="$(dig +short TXT "${DOMAIN}" | tr -d '"' | grep -i '^v=spf1' | head -1)"
if [ -n "${spf}" ]; then
    pass "SPF published"
    echo "        ${spf}"
    echo "${spf}" | grep -qi -- '-all' \
        && echo "        strict (-all): good" \
        || echo "        soft (~all): spoofers still get a chance"
else
    fail "No SPF record — receiving servers cannot confirm your mail is genuine"
fi

# --- Sending: DKIM -----------------------------------------------------------
dkim_found=0
for selector in ${DKIM_SELECTORS}; do
    if dig +short TXT "${selector}._domainkey.${DOMAIN}" | grep -q 'p='; then
        pass "DKIM selector '${selector}' present"
        dkim_found=1
    fi
done
[ "${dkim_found}" -eq 0 ] && fail "No DKIM key at the usual selectors — ask your mail provider for its exact selector"

# --- Policy: DMARC -----------------------------------------------------------
dmarc="$(dig +short TXT "_dmarc.${DOMAIN}" | tr -d '"' | grep -i '^v=DMARC1' | head -1)"
if [ -n "${dmarc}" ]; then
    pass "DMARC published"
    echo "        ${dmarc}"
    echo "${dmarc}" | grep -qi 'p=none' \
        && warn "policy is p=none (monitor only) — tighten to quarantine once reports are clean"
else
    fail "No DMARC record — no policy, no reporting, weaker inbox placement"
fi

# --- Identity ----------------------------------------------------------------
echo
echo "Brand identity"
if [ -n "${mx}" ] && [ -n "${spf}" ] && [ "${dkim_found}" -eq 1 ] && [ -n "${dmarc}" ]; then
    pass "${DOMAIN} can both send and receive; safe to use for social accounts"
else
    fail "Not ready to be published as a brand address yet"
fi

# --- Which mailbox provider is answering -------------------------------------
# Zoho runs separate data centres with different host names, so "is it Zoho"
# is really "which Zoho". This matters because MX and SPF must name the same
# region, and a mismatch silently drops mail.
if [ -n "${mx}" ]; then
    echo
    echo "Mailbox provider"
    case "${mx}" in
        *zoho.eu*)    echo "        Zoho Mail, EU data centre (mx.zoho.eu, include:zoho.eu)" ;;
        *zoho.in*)    echo "        Zoho Mail, India data centre (mx.zoho.in, include:zoho.in)" ;;
        *zoho.com*)   echo "        Zoho Mail, US data centre (mx.zoho.com, include:zoho.com)" ;;
        *zoho*)       echo "        Zoho Mail, other region — copy MX and SPF from the admin console" ;;
        *afeeshost*|*afees*) echo "        AfeesHost cPanel mail (host-managed SPF/DKIM)" ;;
        *)            echo "        Unrecognised: $(echo "${mx}" | head -1)" ;;
    esac

    if [ -n "${spf}" ] && echo "${mx}" | grep -qi zoho; then
        if echo "${spf}" | grep -qi 'include:zoho'; then
            pass "SPF authorises Zoho"
        else
            fail "MX points at Zoho but SPF does not include it — Zoho must be able to send as this domain"
        fi
    fi
fi
