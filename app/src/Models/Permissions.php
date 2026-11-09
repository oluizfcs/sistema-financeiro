<?php declare(strict_types=1);

namespace App\Models;

class Permissions
{
    const ADMIN               = 1 << 0;
    const CREATE_FUTURE_BILLS = 1 << 1;

    public const LABELS = [
        self::ADMIN => 'Administrador',
        self::CREATE_FUTURE_BILLS => 'Cadastrar lançamentos futuros'
    ];

    public static function userCan(int $userPermissions, int $permissionBit): bool
    {
        if (($userPermissions & Permissions::ADMIN) === Permissions::ADMIN) return true;
        return ($userPermissions & $permissionBit) === $permissionBit;
    }

    public static function getLabel(int $permissionBit): string
    {
        return self::LABELS[$permissionBit] ?? 'Permissão inválida';
    }

    public static function getLabels(int $userPermissions): array
    {
        $labels = [];
        foreach(self::LABELS as $bit => $label) {
            if (($userPermissions & $bit) === $bit) $labels[] = $label;
        }
        return $labels;
    }

    public static function diff(int $oldPermissions, int $newPermissions): array
    {
        $changedBits = $oldPermissions ^ $newPermissions;
        if ($changedBits === 0) return ['granted' => [], 'revoked' => []];

        $grantedBits = $changedBits & $newPermissions;
        $revokedBits = $changedBits & $oldPermissions;

        $granted = [];
        $revoked = [];

        foreach (self::LABELS as $bit => $label) {
            if (($grantedBits & $bit) === $bit) $granted[] = $label;
            if (($revokedBits & $bit) === $bit) $revoked[] = $label;
        }

        return ['granted' => $granted, 'revoked' => $revoked];
    }
}
