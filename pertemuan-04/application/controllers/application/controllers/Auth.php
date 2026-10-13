<?php

class Auth extends Controller
{
    public function login(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // GET: tampilkan halaman login
        if ($method === 'GET') {
            $this->view('auth/login');
            return;
        }

        // Selain GET dan POST ditolak
        if ($method !== 'POST') {
            http_response_code(405);
            header('Allow: GET, POST');
            exit('Method Not Allowed');
        }

        // Ambil dan normalisasi input
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validasi username
        if ($username === '' || strlen($username) > 15 || preg_match('/[\x00-\x1F\x7F]/', $username)) {
            $this->view('auth/login', [
                'error' => 'Username tidak valid.'
            ]);
            return;
        }

        // Validasi password
        if ($password === '') {
            $this->view('auth/login', [
                'error' => 'Password wajib diisi.'
            ]);
            return;
        }

        // Panggil Model
        require_once APPPATH . 'models/Admin_model.php';
        $model = new Admin_model();
        $admin = $model->findByUsername($username);

        // Verifikasi akun dan password
        if (!$admin || $admin['status_akun'] !== 'aktif' || !password_verify($password, $admin['password'])) {
            $this->view('auth/login', [
                'error' => 'Username atau password tidak sesuai.'
            ]);
            return;
        }

        // Regenerasi session ID setelah login berhasil (Mencegah Session Fixation)
        session_regenerate_id(true);

        // Simpan informasi minimum ke session
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_status'] = $admin['status_akun'];

        // Redirect ke halaman admin
        header('Location: ' . site_url('admin'));
        exit;
    }

    public function logout(): void
    {
        // Logout hanya melalui POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }

        // Hapus seluruh data session
        $_SESSION = [];

        // Hapus session cookie jika digunakan
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Hancurkan session secara permanen
        session_destroy();

        // Kembali ke halaman login
        header('Location: ' . site_url('auth/login'));
        exit;
    }
}
