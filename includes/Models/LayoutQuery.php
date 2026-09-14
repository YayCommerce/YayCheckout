<?php
namespace YayCheckout\Models;

defined( 'ABSPATH' ) || exit;

/**
 * How to ask for layouts.
 *
 * Speaks product lifecycle only, never WordPress post_status. Search and
 * pagination belong to the admin UI over the returned list, not here.
 */
class LayoutQuery {

    /**
     * @var string[]
     */
    private array $statuses;

    /**
     * @param string[] $statuses
     * @throws \InvalidArgumentException When $statuses is empty or contains an unknown lifecycle.
     */
    private function __construct( array $statuses ) {
        $unique = array_values( array_unique( $statuses ) );

        if ( $unique === [] ) {
            throw new \InvalidArgumentException( 'At least one layout status is required.' );
        }

        $allowed = [
            Layout::LIFECYCLE_DRAFT,
            Layout::LIFECYCLE_PUBLISHED,
            Layout::LIFECYCLE_ARCHIVED,
        ];

        foreach ( $unique as $status ) {
            if ( ! in_array( $status, $allowed, true ) ) {
                throw new \InvalidArgumentException( sprintf( 'Unknown layout status: %s', $status ) );
            }
        }

        $this->statuses = $unique;
    }

    /**
     * The Layouts home tab: everything the merchant works with, not archived.
     */
    public static function listed(): self {
        return new self( [ Layout::LIFECYCLE_DRAFT, Layout::LIFECYCLE_PUBLISHED ] );
    }

    public static function archived(): self {
        return new self( [ Layout::LIFECYCLE_ARCHIVED ] );
    }

    public static function any(): self {
        return new self(
            [
                Layout::LIFECYCLE_DRAFT,
                Layout::LIFECYCLE_PUBLISHED,
                Layout::LIFECYCLE_ARCHIVED,
            ]
        );
    }

    /**
     * @param  string[] $lifecycles
     * @return self
     * @throws \InvalidArgumentException When $lifecycles is empty or contains an unknown lifecycle.
     */
    public static function of_statuses( array $lifecycles ): self {
        return new self( $lifecycles );
    }

    /**
     * @return string[]
     */
    public function statuses(): array {
        return $this->statuses;
    }
}
