# EASYPOS architecture and security

The Laravel application serves the Filament back office and the POS, menu, and kitchen stations. Floor routes check station permissions on the server. A PIN-authenticated session is restricted from the back office.

## Roles and branch isolation

The owner and super-admin roles manage operations across branches through the back office. Floor stations have separate permissions. A branch-manager can manage floor staff in their own branch and is denied cross-branch staff management. For branch-scoped operations, the authenticated manager's assigned branch_id determines which records they may manage.

A cashier uses the POS register. A cook works kitchen tickets, and a chef uses the menu and kitchen stations.

## Transport and sessions

When a load balancer terminates TLS, it forwards the original HTTPS scheme in X-Forwarded-Proto. Laravel accepts that header from configured trusted proxies. The security middleware sends Strict-Transport-Security (HSTS) on secure requests.

The environment template enables session encryption and Secure cookies. HttpOnly prevents scripts from reading the session cookie, and SameSite=Lax limits when browsers include it on cross-site requests.

The terminal lock records the locked state in the session. Middleware checks that state on subsequent requests, including Livewire updates, so reloading the station keeps it locked until the user unlocks it or signs out.
