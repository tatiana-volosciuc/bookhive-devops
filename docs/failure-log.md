# Failure Log — Security Group Practice

Format: one entry per deliberate break/fix cycle. Fill in **Symptom** and **Hypothesis** the moment you notice something's wrong — before checking the actual cause.

---

## Entry 1

- **Symptom:** `Target.Timeout` on both targets in the target group, even though app-sg, NACLs, the listener, and the application itself were all checked and confirmed fine.
- **Hypothesis:** The problem is in the security group reference between `app-sg` and `alb-sg`.
- **Actual cause:** The ALB was attached to the VPC's default security group (self-reference ingress only), not to the properly configured `bookhive-alb-sg`. I never explicitly selected a security group when creating the ALB.
- **Time to diagnose:** ~30 minutes

---

## Entry 2

- **Symptom:** Target health turned healthy, but `curl` from outside hung (TCP connect timeout, not "connection refused").
- **Hypothesis:** DNS isn't resolving, or the problem is on my local network.
- **Actual cause:** The inbound rule on `bookhive-alb-sg` (80/443 from `0.0.0.0/0`) was missing — likely lost during earlier security group edits. Running `curl example.com` confirmed my own network was fine, which narrowed the problem down to `alb-sg` itself.
- **Time to diagnose:** ~15 minutes

---

## Entry 3

- **Symptom:** Both targets in the target group flipped to unhealthy with `Reason: Target.Timeout` after modifying `app-sg`.
- **Hypothesis:** Removing `alb-sg`'s inbound rule from `app-sg` would cut the instances off from outbound internet access over HTTP/HTTPS.
- **Actual cause:** The hypothesis had the direction backwards. What was removed was the **inbound** rule (who can reach the instance), not egress (where the instance can reach out to) so egress was untouched. The real cause: the ALB could no longer reach into the app instances on port 80, because `alb-sg` had been removed as an allowed source in `app-sg`'s inbound rules. Packets from the ALB were silently dropped at the security group level — hence `Target.Timeout` rather than "connection refused."
- **Time to diagnose:** ~15 minutes (from `revoke-security-group-ingress` to healthy again after re-authorizing)

---

## Entry 4

- **Symptom:** After restoring the security group rule, one target (`i-0650e05fd6473b209`) stayed stuck in `Target.FailedHealthChecks` longer than the expected ~2.5-minute cycle (5 consecutive successful checks).
- **Hypothesis:** The security group fix hadn't applied correctly, or the problem had shifted to NACLs/routing.
- **Actual cause:** Not a networking issue at all. The test HTTP server (`python3 -m http.server 80 &`) had been started in the background inside an SSM session, and it died along with that session when the session was closed without running `exit` cleanly. `curl localhost:80` from inside the instance returned "Connection refused" (0ms) so it's a clear signal that the port was closed and nothing was listening, as opposed to `Target.Timeout` (network blocking, packet never arrives). Restarting the server resolved it.
- **Time to diagnose:** ~10 minutes (including diagnosis via SSM + `curl localhost`)

---
