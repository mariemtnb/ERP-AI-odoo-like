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

- [ ] **S1 - Global API rate limiting.** Attach the `api` rate limiter to every
  API route (not just the hand-picked ones), so a flood on any endpoint is
  capped. Keep the tighter per-route throttles on auth and sensitive actions.
- [ ] **S2 - HTTPS hardening + file-upload hardening.** Send an HSTS header in
  production; add a size cap to the bank-import file and a mime rule to the
  import preview.
- [ ] **S3 - Assistant step cap.** Give the agent a hard limit on reasoning
  steps / tool calls per request, so a single question can never loop
  unbounded (the local-model equivalent of a spend cap).

Each item ships with a test where the backend can express one.
