<?php
namespace YayCheckout\Models;

use YayCheckout\Enums\LayoutStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Speaks LayoutStatus only, never post_status.
 */
class LayoutQuery {

    /** Fixed server-side; not a request param. */
    public const PER_PAGE = 10;

    /** @var string[] */
    private array $statuses;

    private string $search = '';

    private int $page = 1;

    /**
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

    public function with_search( string $search ): self {
        $clone         = clone $this;
        $clone->search = trim( $search );

        return $clone;
    }

    public function with_pagination( int $page ): self {
        $clone       = clone $this;
        $clone->page = max( 1, $page );

        return $clone;
    }

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

    public function search(): string {
        return $this->search;
    }

    public function page(): int {
        return $this->page;
    }

    public function per_page(): int {
        return self::PER_PAGE;
    }
}
