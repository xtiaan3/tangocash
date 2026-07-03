-- 0004_force_signout_at — add force_signout_at to tc_users.
--
-- Set by /auth/disconnect.php when BrainLock fires its disconnect
-- webhook — i.e. the user revoked the TangoCash connection from
-- brainlock.id/connections (or any other partner-initiated unbind
-- fired by BrainLock). The front-controller (_bootstrap.php) checks
-- this on every request and destroys the PHP session if the
-- timestamp post-dates when the session was issued.
--
-- That's how a server-side webhook from BrainLock kicks a user out
-- of TC across every device — not just the one they happened to be
-- on when they clicked Remove. The next page load on any other
-- device hits the check and gets signed out.

ALTER TABLE tc_users
  ADD COLUMN force_signout_at DATETIME NULL DEFAULT NULL AFTER last_signin_at;
