<?php
namespace YayCheckout\Models;

use YayCheckout\Enums\LayoutStatus;

defined( 'ABSPATH' ) || exit;

/**
 * How to ask for layouts.
 *
 * Speaks LayoutStatus only, never WordPress post_status. Search and
 * pagination belong to the admin UI over the returned list, not here.
 */
class LayoutQuery {

    /**
     * @var string[]
     */
    private array $statuses;

    /**
     * @param string[] $statuses
     * @throws \InvalidArgumentException When $statuses is empty or contains an unknown status.
     */
    private function __construct( array $statuses ) {
        $unique = array_values( array_unique( $statuses ) );

        if ( $unique === [] ) {
            throw new \InvalidArgumentException( 'At least one layout status is required.' );
        }

        foreach ( $unique as $status ) {
            if ( ! LayoutStatus::is_valid( $status ) ) {
                throw new \InvalidArgumentException( sprintf( 'Unknown layout status: %s', $status ) );
            }
        }

        $this->statuses = $unique;
    }

    /**
     * The Layouts home tab: everything the merchant works with, not archived.
     */
    public static function listed(): self {
        return new self( LayoutStatus::listed() );
    }

    public static function archived(): self {
        return new self( [ LayoutStatus::ARCHIVED ] );
    }

    public static function any(): self {
        return new self( LayoutStatus::all() );
    }

    /**
     * @param  string[] $statuses
     * @return self
     * @throws \InvalidArgumentException When $statuses is empty or contains an unknown status.
     */
    public static function of_statuses( array $statuses ): self {
        return new self( $statuses );
    }

    /**
     * @return string[]
     */
    public function statuses(): array {
        return $this->statuses;
    }
}
