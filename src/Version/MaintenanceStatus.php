<?php

namespace App\Version;

/**
 * Maintenance tier for a Composer release line.
 *
 * The cases form a degrading ladder: a release line starts at Bugfix while it
 * is the active latest release, then drops to Security, then CriticalSecurity
 * as it nears end of life, and finally Eol once it is no longer maintained.
 *
 * The string values are a public contract exposed in web/versions and consumed
 * by Composer's self-update/installer.
 */
enum MaintenanceStatus: string
{
    case Bugfix = 'bugfix';
    case Security = 'security';
    case CriticalSecurity = 'critical-security';
    case Eol = 'eol';

    /**
     * Short human-readable label for the download page.
     */
    public function label(): string
    {
        return match ($this) {
            self::Bugfix => 'Bug & security fixes',
            self::Security => 'Security fixes only',
            self::CriticalSecurity => 'Critical security fixes only',
            self::Eol => 'End of life',
        };
    }

    /**
     * Longer description explaining what users can expect from this tier.
     */
    public function description(): string
    {
        return match ($this) {
            self::Bugfix => 'Active release. Receives all bug fixes and security fixes.',
            self::Security => 'Receives security fixes only, no general bug fixes.',
            self::CriticalSecurity => 'Receives only critical security fixes, nearing end of life.',
            self::Eol => 'No longer maintained, no fixes of any kind are provided.',
        };
    }
}
