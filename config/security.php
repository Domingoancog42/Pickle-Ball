<?php
// Defaults; saved administrator Security settings override the matching values.
return array (
  'minimum_password_length' => 12,
  'maximum_password_length' => 64,
  'password_expiry_days' => 90,
  'require_expired_reset' => 0,
  'session_timeout_minutes' => 30,
  'lockout_failed_attempts' => 5,
  'lockout_duration_minutes' => 15,
  'invalidate_sessions' => 1,
  'login_notifications' => 0,
  'audit_retention_days' => 365,
);
