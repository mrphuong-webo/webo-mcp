---
name: webo-mcp-ultimo-domain-dns-cf
description: >-
  When a WP Ultimo custom domain on network.webo.vn (or similar) is stuck in
  stage checking-dns or inactive/unsecured: use WEBO MCP network tools first, then
  validate Cloudflare DNS/SSL/proxy in the dashboard and public DNS. Use after
  webo-mcp-site-foundation site creation or domain recheck failures.
---

# WEBO MCP — Ultimo `checking-dns` + Cloudflare checklist

## When to use

- Ultimo domain row shows **`stage: checking-dns`**, **`active: false`**, or **`secure: false`**.
- After **`webo-ultimo/sites-mutate`** `create-site` with a custom domain.
- User asks why the domain is not live, SSL not issued, or “network” errors.

## MCP flow (network router)

Router pattern: `https://network.webo.vn/wp-json/mcp/v1/router` (or your network host).

1. `initialize` → keep `session_id`.
2. `tools/list` — confirm tool names (unified Ultimo tools vary by version).
3. Call **`webo-ultimo/domains-mutate`** with **`action: recheck-domain`**; pass
   **`domain_id`** and/or **`site_id`** as required by the tool schema.
4. Call **`webo-ultimo/infra-ops`** when available:
   - **`sync-aapanel-domain`** — confirms/chases hosting bind (aaPanel); read `result`
     and `last_error`. A resolved aaPanel sync does **not** replace DNS checks.
   - **`test-aapanel-connection`** — sanity check API connectivity.
   - **`cloudflare-sync-status`** — see last CF sync messages for the stack.
5. Record in the report: domain `id`, `stage`, `active`, `secure`, queue/cron hints,
   aaPanel payload, and any CF sync lines.

## Cloudflare dashboard (if the zone uses CF)

Work in the **correct account/zone** for this domain.

| Check | Why it matters |
|-------|----------------|
| Nameservers / zone ownership | Domain must use CF NS (or the documented split-horizon setup); wrong NS → Ultimo never sees your records. |
| A / AAAA / CNAME for `@` and `www` | Must match Webo’s documented target (origin or CNAME to the network). Duplicates or wrong IP keep `checking-dns`. |
| Proxy (orange vs grey) | Wrong mode can break origin verification or SSL issuance per host policy. |
| SSL/TLS encryption mode | Flexible vs Full mismatches cause redirect/SSL loops; not always visible as a WP error. |
| Redirect rules, Page Rules, bulk redirects | Can send traffic away from the multisite vhost or block validation. |

## Public DNS

Confirm from an external resolver (not only office LAN): apex and `www` resolve to
the expected targets and TTL has expired after changes.

## After fixes

Wait for DNS TTL, run **`recheck-domain`** again, re-read `stage` / `active` /
`secure`.

## MCP payload: `dns_expectation` (webo-mcp-ultimo 1.1.8+)

When a domain row has Ultimo stage **`checking-dns`** or **`checking-ssl-cert`**, MCP
readbacks that use `webo_mcp_ultimo_format_domain()` include an extra field:

- **`dns_expectation`** — `network_primary_domain`, `suggested_dns` (empty until you
  configure it), `cloudflare_dashboard_checks`, `ultimo_stage_note`.

**Populate concrete A/CNAME targets** on the network site (must not live in public
plugin code):

```php
add_filter( 'webo_mcp_ultimo_dns_expectation', function ( $data, $host, $site_id ) {
    $data['suggested_dns'][] = array(
        'type'   => 'A',
        'name'   => '@',
        'target' => '203.0.113.50', // example: edge or origin IP documented for Webo
    );
    return $data;
}, 10, 3 );
```

Optional next releases: **`cloudflare-dns-snapshot`** (read-only CF API) or
**checking-dns substatus** when the stack can classify NXDOMAIN vs wrong IP.
