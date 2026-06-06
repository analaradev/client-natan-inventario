<?php

namespace App\Helpers;

class AuthHelper
{
    /**
     * Verifica si el usuario actual es administrador
     * 
     * @return bool
     */
    public static function isAdmin(): bool
    {
        $roles = session('roles', []);
        
        if (empty($roles)) {
            return false;
        }
        
        foreach ($roles as $rol) {
            $rolNombre = self::normalizeRoleName($rol['ROL'] ?? $rol['rol'] ?? '');
            $rolId = $rol['ID'] ?? $rol['id'] ?? $rol['ROL_ID'] ?? $rol['rol_id'] ?? null;

            if (self::hasFullInventoryRole($rolNombre, $rolId)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verifica si el usuario actual es Admin Libreria (para control de vistas)
     * Solo los usuarios con rol "ADMIN LIBRERIA" pueden modificar datos
     * 
     * @return bool
     */
    public static function isAdminLibreria(): bool
    {
        $roles = session('roles', []);
        
        if (empty($roles)) {
            return false;
        }
        
        foreach ($roles as $rol) {
            $rolNombre = self::normalizeRoleName($rol['ROL'] ?? $rol['rol'] ?? '');
            $rolId = $rol['ID'] ?? $rol['id'] ?? $rol['ROL_ID'] ?? $rol['rol_id'] ?? null;

            if (
                $rolNombre === 'ADMIN LIBRERIA' ||
                (string) $rolId === '19'
            ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verifica si el usuario tiene acceso al sistema (Admin Librería o Supervisor)
     * Solo estos roles pueden ingresar al sistema
     * 
     * @return bool
     */
    public static function canAccessSystem(): bool
    {
        $roles = session('roles', []);
        
        if (empty($roles)) {
            return false;
        }
        
        foreach ($roles as $rol) {
            $rolNombre = self::normalizeRoleName($rol['ROL'] ?? $rol['rol'] ?? '');
            $rolId = $rol['ID'] ?? $rol['id'] ?? $rol['ROL_ID'] ?? $rol['rol_id'] ?? null;

            if (self::hasFullInventoryRole($rolNombre, $rolId)) {
                return true;
            }
        }
        
        return false;
    }

    private static function hasFullInventoryRole(string $roleName, $roleId): bool
    {
        return $roleName === 'ADMIN LIBRERIA' ||
            $roleName === 'SUPERVISOR' ||
            (string) $roleId === '19' ||
            (string) $roleId === '20';
    }

    private static function normalizeRoleName(string $roleName): string
    {
        $roleName = strtoupper(trim($roleName));

        return strtr($roleName, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'á' => 'A',
            'é' => 'E',
            'í' => 'I',
            'ó' => 'O',
            'ú' => 'U',
        ]);
    }
}
