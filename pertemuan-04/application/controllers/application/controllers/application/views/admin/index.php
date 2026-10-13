<p>
  Login sebagai:
  <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong>
</p>

<form method="post"
  action="<?= htmlspecialchars(site_url('auth/logout'), ENT_QUOTES, 'UTF-8') ?>">
  <button type="submit" class="btn btn-danger">Logout</button>
</form>