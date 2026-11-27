<?php
/**
 * Omnisend order transformer
 *
 * @package OmnisendSureCartPlugin
 */

declare(strict_types=1);

namespace Omnisend\SureCartAddon\Transformers\Events;

use SureCart\Models\Order;
use SureCart\Models\Checkout;
use Omnisend\SureCartAddon\Service\SureCartModelService;
use Omnisend\SureCartAddon\Converter\ValueConverter;
use Omnisend\SureCartAddon\Transformers\ContactTransformer;
use Omnisend\SureCartAddon\Transformers\Events\Components\DiscountTransformer;
use Omnisend\SureCartAddon\Transformers\Events\Components\AddressTransformer;
use Omnisend\SureCartAddon\Transformers\Events\Components\TrackingTransformer;
use Omnisend\SureCartAddon\Transformers\Events\Components\LineItemTransformer;
use Omnisend\SDK\V1\Events\PlacedOrder as OmnisendPlacedOrder;
use Omnisend\SDK\V1\Events\PaidForOrder as OmnisendPaidForOrder;
use Omnisend\SDK\V1\Events\OrderCanceled as OmnisendCanceledOrder;
use Omnisend\SDK\V1\Events\OrderFulfilled as OmnisendFulfilledOrder;
use Omnisend\SDK\V1\Events\OrderRefunded as OmnisendRefundedOrder;
use Omnisend\SDK\V1\Event as OmnisendEvent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OrderTransformer
 */
class OrderTransformer {
	/**
	 * @var string
	 */
	private $event_name = null;

	/**
	 * @var SureCartModelService
	 */
	private $sure_cart_model;

	/**
	 * @var ValueConverter
	 */
	private $value_converter;

	/**
	 * @var ContactTransformer
	 */
	private $contact_transformer;

	/**
	 * @var DiscountTransformer
	 */
	private $discount_transformer;

	/**
	 * @var AddressTransformer
	 */
	private $address_transformer;

	/**
	 * @var TrackingTransformer
	 */
	private $tracking_transformer;

	/**
	 * @var LineItemTransformer
	 */
	private $line_item_transformer;

	public function __construct() {
		$this->sure_cart_model       = new SureCartModelService();
		$this->value_converter       = new ValueConverter();
		$this->contact_transformer   = new ContactTransformer();
		$this->discount_transformer  = new DiscountTransformer();
		$this->address_transformer   = new AddressTransformer();
		$this->tracking_transformer  = new TrackingTransformer();
		$this->line_item_transformer = new LineItemTransformer();
	}

	/**
	 * Transforms SureCart orders to Omnisend event orders
	 *
	 * @param array  $orders
	 * @param string $event_name
	 *
	 * @return array
	 */
	public function transform_orders( array $orders, string $event_name ): array {
		$omnisend_events = array();
		$with_event_time = true;

		foreach ( $orders as $order ) {
			$omnisend_events[] = $this->transform_order( $order, $event_name, $with_event_time );
		}

		return $omnisend_events;
	}

	/**
	 * Transform SureCart order to Omnisend event order
	 *
	 * @param Order  $order
	 * @param string $event_name
	 * @param bool   $with_event_time
	 *
	 * @return OmnisendEvent
	 */
	public function transform_order( Order $order, string $event_name, bool $with_event_time = false ): OmnisendEvent {
		$this->event_name = $event_name;
		$omnisend_event   = new OmnisendEvent();

		$email      = $order->getAttribute( 'checkout' )->getAttribute( 'inherited_email' );
		$contact    = $this->contact_transformer->transform_contact_for_event( $email );
		$properties = $this->get_omnisend_properties( $order );

		$omnisend_event->set_event_version( 'v2' );
		$omnisend_event->set_recommended_event( $properties );
		$omnisend_event->set_contact( $contact );

		if ( $with_event_time ) {
			$omnisend_event->set_event_time(
				$this->value_converter->convert_timestamp(
					$order->getAttribute( 'checkout' )->getAttribute( 'created_at' )
				)
			);
		}

		return $omnisend_event;
	}

	/**
	 * Retrieves Omnisend order properties for event
	 *
	 * @param Order $order
	 *
	 * @return ?object
	 */
	private function get_omnisend_properties( Order $order ): ?object {
		$this->omnisend_order = $this->get_omnisend_event_object();

		if ( ! $this->omnisend_order ) {
			return $this->omnisend_order;
		}

		$checkout_data = $order->getAttribute( 'checkout' );

		$this->add_order_data( $order );
		$this->add_order_items( $order );
		$this->add_address( $checkout_data );
		$this->add_totals( $checkout_data );
		$this->add_discounts( (string) $checkout_data->getAttribute( 'discount' ) );
		$this->add_conditional_data( $order );
		$this->add_tracking( $order );

		return $this->omnisend_order;
	}

	/**
	 * Adds to Omnisend object generic data
	 *
	 * @param Order $order
	 *
	 * @return void
	 */
	private function add_order_data( Order $order ): void {
		$checkout_data          = $order->getAttribute( 'checkout' );
		$shipping_method_choice = $checkout_data->getAttribute( 'selected_shipping_choice' );
		$shipping_method_choice = is_object( $shipping_method_choice ) ? $this->get_shipping_method( $shipping_method_choice ) : 'None';

		$this->omnisend_order->set_id( $checkout_data->getAttribute( 'id' ) );
		$this->omnisend_order->set_number( $this->get_order_number( (string) $checkout_data->getAttribute( 'number' ) ) );
		$this->omnisend_order->set_currency( strtoupper( $checkout_data->getAttribute( 'currency' ) ) );
		$this->omnisend_order->set_created_at( $this->value_converter->convert_timestamp( $checkout_data->getAttribute( 'created_at' ) ) );
		$this->omnisend_order->set_shipping_method( $shipping_method_choice );
		$this->omnisend_order->set_payment_method( $this->get_payment_method( $checkout_data ) );
		$this->omnisend_order->set_fulfillment_status( $this->get_order_status( $order->getAttribute( 'fulfillment_status' ) ) );
		$this->omnisend_order->set_payment_status( $this->get_payment_status( $order->getAttribute( 'status' ) ) );
	}

	/**
	 * Adds to Omnisend object totals
	 *
	 * @param Checkout $checkout_data
	 *
	 * @return void
	 */
	private function add_totals( Checkout $checkout_data ): void {
		$this->omnisend_order->set_shipping_price( $this->value_converter->convert_price( (int) $checkout_data->getAttribute( 'shipping_amount' ) ) );
		$this->omnisend_order->set_subtotal_price( $this->value_converter->convert_price( (int) $checkout_data->getAttribute( 'subtotal_amount' ) ) );
		$this->omnisend_order->set_subtotal_tax_included( false );
		$this->omnisend_order->set_total_discount( abs( $this->value_converter->convert_price( (int) $checkout_data->getAttribute( 'discount_amount' ) ) ) );
		$this->omnisend_order->set_total_price( $this->value_converter->convert_price( (int) $checkout_data->getAttribute( 'total_amount' ) ) );
		$this->omnisend_order->set_total_tax( $this->value_converter->convert_price( (int) $checkout_data->getAttribute( 'tax_amount' ) ) );
	}

	/**
	 * Adds to Omnisend object address
	 *
	 * @param Checkout $checkout_data
	 *
	 * @return void
	 */
	private function add_address( Checkout $checkout_data ): void {
		$billing_is_shipping = $checkout_data->getAttribute( 'billing_matches_shipping' );
		$shipping_address    = $checkout_data->getAttribute( 'shipping_address' );
		$billing_address     = $checkout_data->getAttribute( 'billing_address' );
		$billing_address     = $billing_is_shipping ? $shipping_address : $billing_address;
		$first_name          = $checkout_data->getAttribute( 'first_name' );
		$last_name           = $checkout_data->getAttribute( 'last_name' );

		$omnisend_address = $this->address_transformer->transform_address(
			$shipping_address,
			$billing_address,
			$first_name,
			$last_name
		);

		$this->omnisend_order->set_address( $omnisend_address );
	}

	/**
	 * Adds to Omnisend object line items
	 *
	 * @param Order $order
	 *
	 * @return void
	 */
	private function add_order_items( Order $order ): void {
		$refund_items = $this->get_refund_list( $order );
		$line_items   = $order->getAttribute( 'checkout' )->getAttribute( 'line_items' )->data;

		foreach ( $line_items as $item ) {
			$line_item = $this->line_item_transformer->transform_order_item( $item );

			if ( in_array( $item->getAttribute( 'id' ), $refund_items ) ) {
				$this->omnisend_order->add_refunded_line_item( $line_item );
			}

			$this->omnisend_order->add_line_item( $line_item );
		}
	}

	/**
	 * Adds to Omnisend object discounts
	 *
	 * @param string $code
	 *
	 * @return void
	 */
	private function add_discounts( string $code ): void {
		if ( empty( $code ) ) {
			return;
		}

		$discount = $this->sure_cart_model->get_discount( $code );

		if ( ! $discount ) {
			return;
		}

		$omnisend_discount = $this->discount_transformer->transform_discount( $discount );
		$this->omnisend_order->add_discount( $omnisend_discount );
	}

	/**
	 * Adds to Omnisend object conditional data by event type
	 *
	 * @param Order $order
	 *
	 * @return void
	 */
	private function add_conditional_data( Order $order ): void {
		if ( $this->event_name === OmnisendCanceledOrder::EVENT_NAME ) {
			$this->omnisend_order->set_cancel_reason( 'unknown' );
		} elseif ( $this->event_name === OmnisendRefundedOrder::EVENT_NAME ) {
			$this->omnisend_order->set_total_refunded_amount( $this->value_converter->convert_price( (int) $order->refund_amount ) );
			$this->omnisend_order->set_total_refunded_tax_amount( 0 );
		}
	}

	/**
	 * Adds to Omnisend object tracking
	 *
	 * @param Order $order
	 *
	 * @return void
	 */
	private function add_tracking( Order $order ): void {
		$fulfillments = $order->getAttribute( 'fulfillments' );

		if ( $fulfillments === null || ! property_exists( $fulfillments, 'data' ) ) {
			return;
		}

		foreach ( $fulfillments->data as $tracking ) {
			if ( empty( $tracking->trackings ) ) {
				continue;
			}

			$tracking = $tracking->trackings->data;

			if ( empty( $tracking ) ) {
				continue;
			}

			$tracking = reset( $tracking );

			$omnisend_tracking = $this->tracking_transformer->transform_tracking( $tracking, $this->event_name );

			$this->omnisend_order->set_tracking( $omnisend_tracking );
		}
	}

	/**
	 * Gets list of item IDs which were refunded
	 *
	 * @param Order $order
	 *
	 * @return array
	 */
	private function get_refund_list( Order $order ): array {
		$list = array();

		if ( $this->event_name !== OmnisendRefundedOrder::EVENT_NAME ) {
			return $list;
		}

		foreach ( $order->refund_items as $item ) {
			$list[] = $item->line_item;
		}

		return $list;
	}

	/**
	 * Gets order status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	private function get_order_status( string $status ): string {
		if ( $status === 'partially_fulfilled' ) {
			return 'inProgress';
		}

		return $status;
	}

	/**
	 * Gets shipping method name
	 *
	 * @param object $shipping_choice
	 *
	 * @return string
	 */
	private function get_shipping_method( object $shipping_choice ): string {
		if ( property_exists( $shipping_choice, 'shipping_method' ) ) {
			return $shipping_choice->shipping_method->name;
		}

		return 'Unknown';
	}

	/**
	 * Gets payment method name
	 *
	 * @param Checkout $checkout_data
	 *
	 * @return string
	 */
	private function get_payment_method( Checkout $checkout_data ): string {
		$payment = $checkout_data->getAttribute( 'payment_method' );

		return (string) ( $payment ? $payment->getAttribute( 'processor_type' ) : 'manual' );
	}

	/**
	 * Gets payment status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	private function get_payment_status( string $status ): string {
		if ( $status === 'processing' || $status === 'all' || $status === 'paid' ) {
			return 'paid';
		}

		if ( $status === 'void' ) {
			return 'voided';
		}

		return 'awaitingPayment';
	}

	/**
	 * Takes order number, and removes string characters
	 *
	 * @param string $number
	 *
	 * @return int
	 */
	private function get_order_number( string $number ): int {
		return (int) preg_replace( '/\D/', '', $number );
	}

	/**
	 * Gets Omnisend Order object
	 *
	 * @return ?object
	 */
	private function get_omnisend_event_object(): ?object {
		if ( $this->event_name === OmnisendPlacedOrder::EVENT_NAME ) {
			return new OmnisendPlacedOrder();
		} elseif ( $this->event_name === OmnisendPaidForOrder::EVENT_NAME ) {
			return new OmnisendPaidForOrder();
		} elseif ( $this->event_name === OmnisendCanceledOrder::EVENT_NAME ) {
			return new OmnisendCanceledOrder();
		} elseif ( $this->event_name === OmnisendFulfilledOrder::EVENT_NAME ) {
			return new OmnisendFulfilledOrder();
		} elseif ( $this->event_name === OmnisendRefundedOrder::EVENT_NAME ) {
			return new OmnisendRefundedOrder();
		}

		return null;
	}
}
