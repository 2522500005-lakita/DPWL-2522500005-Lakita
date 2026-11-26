<?php
// P9 BARU - application/helpers/csrf_helper.php
// Token CSRF sederhana berbasis session untuk form pembayaran dan verifikasi.
function csrf_token(): string
{
  if (
    empty($_SESSION['_csrf_p9']) ||
    !is_string($_SESSION['_csrf_p9'])
  ) {
    $_SESSION['_csrf_p9'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['_csrf_p9'];
}

function csrf_field(): string
{
  return '<input type="hidden" name="csrf_token" value="'
    . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
    . '">';
}

function csrf_valid(): bool
{
  $dikirim = $_POST['csrf_token'] ?? '';
  $sesi = $_SESSION['_csrf_p9'] ?? '';
  return is_string($dikirim)
    && is_string($sesi)
    && $sesi !== ''
    && hash_equals($sesi, $dikirim);
}