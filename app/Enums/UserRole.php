<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';
    case Waiter = 'waiter';
    case Customer = 'customer';

    /**
     * Get the human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Cashier => 'Kasir',
            self::Kitchen => 'Kitchen/Barista',
            self::Waiter => 'Waiter',
            self::Customer => 'Pelanggan',
        };
    }

    /**
     * Get roles that belong to a tenant (non-super-admin).
     *
     * @return array<self>
     */
    public static function tenantRoles(): array
    {
        return [
            self::Owner,
            self::Manager,
            self::Cashier,
            self::Kitchen,
            self::Waiter,
            self::Customer,
        ];
    }

    /**
     * Get staff roles (non-customer, non-super-admin).
     *
     * @return array<self>
     */
    public static function staffRoles(): array
    {
        return [
            self::Owner,
            self::Manager,
            self::Cashier,
            self::Kitchen,
            self::Waiter,
        ];
    }

    /**
     * Check if this role has management access.
     */
    public function isManagement(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Owner, self::Manager]);
    }

    /**
     * Check if this role can access the POS.
     */
    public function canAccessPos(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Owner, self::Manager, self::Cashier, self::Waiter]);
    }

    /**
     * Check if this role can access the kitchen display.
     */
    public function canAccessKitchen(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Owner, self::Manager, self::Kitchen]);
    }
}
