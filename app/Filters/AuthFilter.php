<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $role = (string) $session->get('role');

        if (! $session->get('isLoggedIn')) {
            return redirect()->to($this->resolveLoginRoute($arguments))->with('error', 'Please login first');
        }

        if ($arguments && ! in_array($role, $arguments, true)) {
            return redirect()->to($this->resolveDashboardRoute($role))->with('error', 'Unauthorized access');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Not needed for this implementation
    }

    private function resolveLoginRoute(?array $arguments): string
    {
        return $arguments !== null && count($arguments) === 1 && in_array('user', $arguments, true)
            ? site_url('buyer/login')
            : site_url('login');
    }

    private function resolveDashboardRoute(string $role): string
    {
        return match ($role) {
            'admin' => site_url('admin/dashboard'),
            'staff' => site_url('staff/dashboard'),
            'user' => site_url('user/dashboard'),
            default => site_url('login'),
        };
    }
}
