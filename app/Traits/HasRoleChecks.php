<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait HasRoleChecks
{
    /**
     * Determina si el usuario en la sesión web tiene rol admin/supervisor.
     */
    protected function isAdmin(): bool
    {
        $roles = session('roles', []);
        if (empty($roles)) {
            return false;
        }
        foreach ($roles as $rol) {
            $rolNombre = '';
            $rolId = null;
            if (is_array($rol)) {
                $rolNombre = $rol['ROL'] ?? $rol['rol'] ?? '';
                $rolId = $rol['ID'] ?? $rol['id'] ?? $rol['ROL_ID'] ?? $rol['rol_id'] ?? $rol['CODROL'] ?? $rol['codrol'] ?? null;
            } else {
                $rolNombre = (string) $rol;
            }
            $rolNombre = $this->normalizeRoleName($rolNombre);
            if ($this->hasFullInventoryRole($rolNombre, $rolId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determina si el request API tiene rol admin/supervisor.
     */
    protected function isAdminFromRequest(Request $request): bool
    {
        $roles = $request->attributes->get('validated_roles');
        if (!is_array($roles)) {
            return false;
        }
        foreach ($roles as $rol) {
            $rolNombre = '';
            $rolId = null;
            if (is_array($rol)) {
                $rolNombre = $rol['ROL'] ?? $rol['rol'] ?? '';
                $rolId = $rol['ID'] ?? $rol['id'] ?? $rol['ROL_ID'] ?? $rol['rol_id'] ?? $rol['CODROL'] ?? $rol['codrol'] ?? null;
            } else {
                $rolNombre = (string) $rol;
            }
            $rolNombre = $this->normalizeRoleName($rolNombre);
            if ($this->hasFullInventoryRole($rolNombre, $rolId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determina si el request API tiene un rol válido para usar la app móvil (vendedor, admin, supervisor).
     */
    protected function hasValidMobileRole(Request $request): bool
    {
        $roles = $request->attributes->get('validated_roles');
        if (!is_array($roles)) {
            return false;
        }
        foreach ($roles as $rol) {
            $rolNombre = '';
            $rolId = null;
            if (is_array($rol)) {
                $rolNombre = $rol['ROL'] ?? $rol['rol'] ?? '';
                $rolId = $rol['ID'] ?? $rol['id'] ?? $rol['ROL_ID'] ?? $rol['rol_id'] ?? $rol['CODROL'] ?? $rol['codrol'] ?? null;
            } else {
                $rolNombre = (string) $rol;
            }
            $rolNombre = $this->normalizeRoleName($rolNombre);
            if ($this->isMobileAuthorizedRole($rolNombre, $rolId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica si es un rol de inventario completo (Admin Librería o Supervisor).
     */
    protected function hasFullInventoryRole(string $roleName, $roleId): bool
    {
        return $roleName === 'ADMIN LIBRERIA' ||
            $roleName === 'SUPERVISOR' ||
            (string) $roleId === '19' ||
            (string) $roleId === '20' ||
            $roleName === '19' ||
            $roleName === '20';
    }

    /**
     * Verifica si es un rol autorizado para el móvil (Vendedor, Admin Librería o Supervisor).
     */
    protected function isMobileAuthorizedRole(string $roleName, $roleId): bool
    {
        return $roleName === 'VENDEDOR' ||
            $roleName === 'ADMIN LIBRERIA' ||
            $roleName === 'SUPERVISOR' ||
            (string) $roleId === '18' ||
            (string) $roleId === '19' ||
            (string) $roleId === '20' ||
            $roleName === '18' ||
            $roleName === '19' ||
            $roleName === '20';
    }

    /**
     * Normaliza el nombre del rol removiendo espacios y acentos.
     */
    protected function normalizeRoleName(string $roleName): string
    {
        $roleName = strtoupper(trim($roleName));
        return strtr($roleName, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U',
        ]);
    }
}
