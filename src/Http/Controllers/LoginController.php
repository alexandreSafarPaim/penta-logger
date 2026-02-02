<?php

namespace PentaLogger\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLoginForm(): Response|RedirectResponse
    {
        if (!$this->isAuthEnabled()) {
            return redirect()->route('penta-logger.dashboard');
        }

        $html = $this->getLoginHtml();
        return response($html)->header('Content-Type', 'text/html');
    }

    public function login(Request $request): RedirectResponse
    {
        $user = $request->input('user');
        $password = $request->input('password');

        $configUser = config('penta-logger.auth.user');
        $configPassword = config('penta-logger.auth.password');

        if ($user === $configUser && $password === $configPassword) {
            $request->session()->put('penta-logger.authenticated', true);
            return redirect()->route('penta-logger.dashboard');
        }

        return redirect()->route('penta-logger.login')
            ->withErrors(['credentials' => 'Invalid credentials'])
            ->withInput(['user' => $user]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('penta-logger.authenticated');
        return redirect()->route('penta-logger.login');
    }

    protected function isAuthEnabled(): bool
    {
        $user = config('penta-logger.auth.user');
        $password = config('penta-logger.auth.password');

        return !empty($user) && !empty($password);
    }

    protected function getLoginHtml(): string
    {
        $error = session('errors')?->first('credentials') ?? '';
        $user = old('user', '');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penta Logger - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #0d1117;
            --bg-secondary: #161b22;
            --bg-tertiary: #21262d;
            --border-color: #30363d;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-blue: #58a6ff;
            --accent-green: #3fb950;
            --accent-red: #f85149;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logo h1 span {
            color: var(--accent-green);
        }

        .logo p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.15s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent-blue);
        }

        .form-group input::placeholder {
            color: var(--text-secondary);
        }

        .error-message {
            background: rgba(248, 81, 73, 0.1);
            border: 1px solid var(--accent-red);
            color: var(--accent-red);
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: none;
            background: var(--accent-green);
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.15s;
        }

        .btn-login:hover {
            opacity: 0.9;
        }

        .btn-login:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                Penta<span>Logger</span>
            </h1>
            <p>Enter your credentials to access the dashboard</p>
        </div>

        {$this->renderError($error)}

        <form method="POST" action="{$this->getLoginUrl()}">
            <input type="hidden" name="_token" value="{$this->getCsrfToken()}">

            <div class="form-group">
                <label for="user">Username</label>
                <input type="text" id="user" name="user" value="{$this->escape($user)}" placeholder="Enter username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>
    </div>
</body>
</html>
HTML;
    }

    protected function renderError(string $error): string
    {
        if (empty($error)) {
            return '';
        }

        return '<div class="error-message">' . $this->escape($error) . '</div>';
    }

    protected function getLoginUrl(): string
    {
        return route('penta-logger.login.submit');
    }

    protected function getCsrfToken(): string
    {
        return csrf_token();
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
