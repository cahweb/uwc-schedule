<?php
namespace CAH\UWC;

/**
 * Enum helper class for Bootstrap alert types
 *
 * Provides consistent references to Bootstrap alert types, to prevent
 * typos.
 *
 * @category CAH
 * @package  UWC
 * @author   Mike W. Leavitt
 * @version  1.0.0
 */
final class AlertTypes
{
    // Using constants so they're immutable
    // (I really wish PHP just had enums)
    public const SUCCESS   = 'success';
    public const INFO      = 'info';
    public const WARNING   = 'warning';
    public const DANGER    = 'danger';
    public const PRIMARY   = 'primary';
    public const SECONDARY = 'secondary';
    public const LIGHT     = 'light';
    public const DARK      = 'dark';
}