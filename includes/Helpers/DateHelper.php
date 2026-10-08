<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Timestamps on the wire. Storage stays a GMT MySQL datetime (core's shape),
 * but that carries no zone marker and a browser parses it as local time;
 * every timestamp leaves as ISO 8601 with an explicit offset. Pure PHP so
 * Models may call it.
 */
class DateHelper {

    private const MYSQL_FORMAT = 'Y-m-d H:i:s';

    /** GMT MySQL datetime → ISO 8601 UTC. Null, empty, the zero date and unparseable input all become null. */
    public static function to_iso8601( ?string $mysql_gmt ): ?string {
        if ( null === $mysql_gmt || '' === $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) {
            return null;
        }

        $date = self::parse( $mysql_gmt, self::MYSQL_FORMAT );

        return null === $date ? null : $date->format( \DateTimeInterface::ATOM );
    }

    /** Strict format check: null when $value isn't exactly $format or names a date that doesn't exist. Parsed as UTC, so the result is only a true instant when $value is GMT. */
    public static function parse( string $value, string $format ): ?\DateTimeImmutable {
        $date = \DateTimeImmutable::createFromFormat( '!' . $format, $value, new \DateTimeZone( 'UTC' ) );

        // createFromFormat rolls 31 Feb over instead of failing.
        return false === $date || $date->format( $format ) !== $value ? null : $date;
    }

    public static function now_iso8601(): string {
        return gmdate( \DateTimeInterface::ATOM );
    }
}
