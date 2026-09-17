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

    /** Null, empty, the zero date and unparseable input all become null. */
    public static function to_iso8601( ?string $mysql_gmt ): ?string {
        if ( null === $mysql_gmt || '' === $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) {
            return null;
        }

        $utc  = new \DateTimeZone( 'UTC' );
        $date = \DateTimeImmutable::createFromFormat( self::MYSQL_FORMAT, $mysql_gmt, $utc );

        // createFromFormat rolls 31 Feb over instead of failing.
        if ( false === $date || $date->format( self::MYSQL_FORMAT ) !== $mysql_gmt ) {
            return null;
        }

        return $date->format( \DateTimeInterface::ATOM );
    }

    public static function now_iso8601(): string {
        return gmdate( \DateTimeInterface::ATOM );
    }
}
