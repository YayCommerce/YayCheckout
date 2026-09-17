<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Timestamps on the wire.
 *
 * Storage keeps WordPress's own shape — a GMT MySQL datetime
 * (`current_time( 'mysql', true )`, `post_date_gmt`) — because that is what
 * core writes and compares. That shape carries no zone marker, so handed to
 * a browser it is parsed as *local* time and lands hours off unless the
 * viewer happens to sit in UTC. Every timestamp the SPA receives goes
 * through here and becomes ISO 8601 with an explicit offset; the SPA's
 * `lib/helpers/date.helper.ts` is the other end and renders it in the
 * viewer's own timezone.
 *
 * Pure PHP so Models may call it (see docs/code-standards.md, Layering).
 */
class DateHelper {

    private const MYSQL_FORMAT = 'Y-m-d H:i:s';

    /**
     * GMT MySQL datetime → ISO 8601 UTC (`2026-09-17T09:19:48+00:00`).
     *
     * Null, empty, the zero date and anything unparseable all become null:
     * to the SPA "no timestamp" is one case, not three.
     *
     * @param  string|null $mysql_gmt
     * @return string|null
     */
    public static function to_iso8601( ?string $mysql_gmt ): ?string {
        if ( null === $mysql_gmt || '' === $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) {
            return null;
        }

        $utc  = new \DateTimeZone( 'UTC' );
        $date = \DateTimeImmutable::createFromFormat( self::MYSQL_FORMAT, $mysql_gmt, $utc );

        // createFromFormat accepts e.g. a 31 Feb by rolling it over; treat that as bad input too.
        if ( false === $date || $date->format( self::MYSQL_FORMAT ) !== $mysql_gmt ) {
            return null;
        }

        return $date->format( \DateTimeInterface::ATOM );
    }

    /**
     * The current moment as ISO 8601 UTC — for "updated at" markers that are
     * only ever shown, never compared against post dates.
     *
     * @return string
     */
    public static function now_iso8601(): string {
        return gmdate( \DateTimeInterface::ATOM );
    }
}
