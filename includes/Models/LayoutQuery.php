<?php
namespace YayCheckout\Models;

use YayCheckout\Enums\LayoutStatus;

defined( 'ABSPATH' ) || exit;

/**
 * How to ask for layouts. Speaks LayoutStatus, which is the post_status vocabulary.
 */
class LayoutQuery {

    /** Fixed server-side; not a request param. */
    public const PER_PAGE = 10;

    /** @var string[] */
    private array $statuses;

    private string $search = '';

    private int $page = 1;

    /** Narrows to the active set; the repository supplies the ids. */
    private bool $active_only = false;

    /** WP_Query orderby key: 'date' (created) or 'modified'. */
    private string $orderby = 'date';

    private string $order = 'DESC';

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

    public function with_active_only( bool $active_only ): self {
        $clone              = clone $this;
        $clone->active_only = $active_only;

        return $clone;
    }

    /**
     * @throws \InvalidArgumentException When $orderby is not 'date' or 'modified', or $order is not ASC or DESC.
     */
    public function with_order( string $orderby, string $order ): self {
        $order = strtoupper( $order );

        if ( ! in_array( $orderby, [ 'date', 'modified' ], true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown layout orderby: %s', $orderby ) );
        }

        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown layout order: %s', $order ) );
        }

        $clone          = clone $this;
        $clone->orderby = $orderby;
        $clone->order   = $order;

        return $clone;
    }

    /** The Layouts home tab: everything not archived. */
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

    public function active_only(): bool {
        return $this->active_only;
    }

    public function orderby(): string {
        return $this->orderby;
    }

    public function order(): string {
        return $this->order;
    }

    public function per_page(): int {
        return self::PER_PAGE;
    }
}
