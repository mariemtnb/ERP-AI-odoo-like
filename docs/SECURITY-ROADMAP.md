# Security checklist - audit and roadmap

Audit of the app against a 20-point security checklist. Most points are already
covered; this document records the evidence for each and lists the genuine gaps
as a small roadmap, developed one by one.

## Audit (20 points)

| # | Point | Status | Evidence / notes |
|---|---|---|---|
| 1 | Hide API keys | Covered | All provider keys read from `env()` (Konnect, Flouci, TTN, Twilio, JWT). No secrets hardcoded in `app/` or `config/`. |
| 2 | Check env variables | Covered | `.env.example` documents every variable; config reads through `env()` only. |
| 3 | Check keys in git | Covered | `.env` is gitignored (`.gitignore`, `backend/.gitignore`); no `.env` file is tracked. |
| 4 | Protect admin routes | Covered | Admin routes are under `role:admin`; other privileged routes under `role:admin,manager`. |
| 5 | Add auth | Covered | JWT authentication on the whole API; unauthenticated requests are rejected. |
| 6 | Check user perms | Covered | RBAC (`EnsureRole`), a permission engine, module allowlists, and field-level visibility. |
| 7 | Sanitize user inputs | Covered | Laravel request validation across controllers; Eloquent binds all values. |
| 8 | Protect against XSS | Covered | React escapes by default; no `dangerouslySetInnerHTML`; the API returns JSON with a locked-down CSP. |
| 9 | SQL injection protect | Covered | Query builder / Eloquent with bindings. The few `selectRaw` fragments use whitelisted, server-chosen constants (validated enums), never raw user input. |
| 10 | Check DB rules | Covered (app layer) | Access is enforced in the app by RBAC; the DB connects with a single app user from `env`. *Deployment:* do not expose the DB port publicly in production. |
| 11 | Add rate limiting | **GAP -> S1** | Per-route throttles exist (auth, sensitive actions), but the global `api` limiter is defined and not attached to the API group, so most endpoints are unthrottled. |
| 12 | Set spend cap | **GAP -> S3** | The assistant runs a local model (no per-call monetary cost), but the agent has no hard cap on reasoning steps per request - a runaway guard is worth adding. |
| 13 | Secure file uploads | Mostly covered -> S2 | OCR and instrument attachments validate `mimes` + `max`; the bank-statement import checks the extension. *Hardening:* add a size cap to the import file and a mime rule to the preview. |
| 14 | CSRF protection | Covered (N/A) | The API is stateless and bearer-token only; it does not authenticate from cookies, so CSRF does not apply. |
| 15 | Check CORS settings | Covered | `config/cors.php` scopes CORS to `api/*`, reads allowed origins from `env`, and disables credentialed cross-origin requests. |
| 16 | Enable HTTPS | **GAP -> S2** | TLS is terminated at the proxy in production, but no `Strict-Transport-Security` (HSTS) header is sent. |
| 17 | Add security headers | Covered | `SecurityHeaders` middleware sets nosniff, `X-Frame-Options: DENY`, Referrer-Policy, Permissions-Policy, a JSON-only CSP, and CORP. (HSTS added in S2.) |
| 18 | Secure cookies | Covered | Session cookies: `secure` from env, `http_only` true, `same_site` lax. *Deployment:* set `SESSION_SECURE_COOKIE=true` in production. |
| 19 | Disable debug mode | Covered | Production compose sets `APP_DEBUG=false`; the dev `.env.example` default of `true` is expected for local only. |
| 20 | Check prod settings | Covered | Production compose sets `APP_ENV=production` and `APP_DEBUG=false`. |

## Roadmap (the real gaps)

- [x] **S1 - Global API rate limiting** - done. The `api` limiter is now
  attached to the whole API group (`throttleApi()`), capping every endpoint at
  `API_RATE_LIMIT` (default 300/min per user or IP); per-route throttles still
  stack. Tested: an endpoint returns 429 once the limit is hit.
- [x] **S2 - HTTPS + upload hardening** - done. An HSTS header
  (`max-age=31536000; includeSubDomains`) is sent on HTTPS requests only; the
  bank-statement import now caps the upload at 5 MB. Tested: HSTS present over
  https, absent over http.
- [x] **S3 - Assistant step cap** - done. The agent's per-request work is
  capped by `AGENT_RECURSION_LIMIT` (default 40 reasoning/tool steps) and
  `AGENT_AUTO_APPROVE_MAX` (default 20 auto-approve loops), now explicit and
  env-tunable - the local-model equivalent of a spend cap.

**All three gaps are closed.** The other 17 checklist points were already
covered (see the audit table above).
