# Google login

Status: **code complete and tested with a faked Google response; not verified against real Google credentials** (none configured). The button stays hidden until `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are set.

## Enable
1. **Run `sql/google_login.sql`** on the shared database (creates `customer_social_accounts`). Not executed yet. Without it, Google sign-in shows a generic error.
2. Google Cloud Console → APIs & Services:
   - OAuth consent screen: configure app name, support email; scopes `openid`, `email`, `profile`; add test users while in "Testing".
   - Credentials → Create credentials → OAuth client ID → *Web application*.
   - **Authorized redirect URIs**: `{APP_URL}/auth/google/callback` — e.g. `http://localhost:8000/auth/google/callback` locally and `https://<your-domain>/auth/google/callback` in production (must match exactly, scheme included). No JavaScript origins needed.
3. `.env`: `GOOGLE_CLIENT_ID=…`, `GOOGLE_CLIENT_SECRET=…` (optionally `GOOGLE_REDIRECT_URI` to override the default `APP_URL` + `/auth/google/callback`). Then `php artisan config:clear`.

## Behaviour (`App\Http\Controllers\GoogleAuthController`)
- `GET /auth/google?return=/path` → Google (state-protected; only same-site relative return paths).
- `GET /auth/google/callback`: handles cancellation (`access_denied`), failures, and requires Google's `email_verified = true`.
- Identity = Google account id in `customer_social_accounts`; repeat logins never depend on email.
- No linked account but the email matches a customer: linked only because Google verified the email. If the local account's email was **never verified**, its password is cleared (someone could have pre-registered the address); the owner sets a new one via "Forgot password". Inactive/blocked accounts are refused.
- Otherwise a new customer is created (`password_hash` NULL, `email_verified_at` set). Email/password login and registration are unchanged; Google-only accounts get a generic "incorrect email or password" on password login until they use password reset.
- Guest cart/wishlist are saved in the browser before the redirect and merged afterwards (`POST /account/merge`).

## Tests
`DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=shaotal_local DB_USERNAME=root DB_PASSWORD= CACHE_STORE=file php artisan test --filter=GoogleLoginTest` (needs the scratch DB with both SQL files; skipped otherwise).
