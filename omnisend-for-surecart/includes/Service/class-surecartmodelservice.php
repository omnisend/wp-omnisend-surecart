<?php
/**
 * Omnisend SureCart model service
 *
 * @package OmnisendSureCartPlugin
 */

declare(strict_types=1);

namespace Omnisend\SureCartAddon\Service;

use SureCart\Models\Product;
use SureCart\Models\ProductCollection;
use SureCart\Models\Order;
use SureCart\Models\Customer;
use SureCart\Models\Discount;
use SureCart\Models\Refund;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SureCartModelService
 */
class SureCartModelService {
	/**
	 * Gets SureCart products
	 *
	 * @param int $page
	 *
	 * @return array
	 */
	public function get_products_by_page( int $page ): array {
		$products = Product::with(
			array(
				'prices',
				'variants',
				'product_collections',
			)
		)->paginate( array( 'page' => $page ) );

		if ( property_exists( $products, 'errors' ) ) {
			return array();
		}

		$products = $products->data;

		if ( is_array( $products ) && ! empty( $products ) ) {
			return $products;
		}

		return array();
	}

	/**
	 * Gets SureCart categories
	 *
	 * @param int $page
	 *
	 * @return array
	 */
	public function get_categories_by_page( int $page ): array {
		$categories = ProductCollection::paginate( array( 'page' => $page ) );

		if ( property_exists( $categories, 'errors' ) ) {
			return array();
		}

		$categories = $categories->data;

		if ( ! empty( $categories ) && is_array( $categories ) ) {
			return $categories;
		}

		return array();
	}

	/**
	 * Gets SureCart orders
	 *
	 * @param int $page
	 *
	 * @return array
	 */
	public function get_orders_by_page( int $page ): array {
		$orders = Order::with(
			array(
				'checkout',
				'checkout.line_items',
				'line_item.price',
				'price.product',
				'checkout.payment_methods',
				'charges',
				'checkout.selected_shipping_choice',
				'shipping_choice.shipping_method',
				'product',
				'product.product_collections',
				'checkout.shipping_address',
				'checkout.billing_address',
			)
		)->paginate( array( 'page' => $page ) );

		if ( property_exists( $orders, 'errors' ) ) {
			return array();
		}

		$orders = $orders->data;

		if ( ! empty( $orders ) && is_array( $orders ) ) {
			return $orders;
		}

		return array();
	}

	/**
	 * Gets SureCart customers
	 *
	 * @param int $page
	 *
	 * @return array
	 */
	public function get_customers_by_page( int $page ): array {
		$customers = Customer::with(
			array(
				'customer.shipping_address',
				'customer.billing_address',
			)
		)->paginate( array( 'page' => $page ) );

		if ( property_exists( $customers, 'errors' ) ) {
			return array();
		}

		$customers = $customers->data;

		if ( ! empty( $customers ) && is_array( $customers ) ) {
			return $customers;
		}

		return array();
	}

	/**
	 * Gets single SureCart order
	 *
	 * @param string $order_id
	 *
	 * @return ?Order
	 */
	public function get_single_order( string $order_id ): ?Order {
		$order = Order::with(
			array(
				'checkout',
				'checkout.line_items',
				'line_item.price',
				'price.product',
				'checkout.payment_methods',
				'charges',
				'checkout.selected_shipping_choice',
				'shipping_choice.shipping_method',
				'product',
				'product.product_collections',
				'checkout.shipping_address',
				'checkout.billing_address',
				'checkout.refunds',
				'fulfillments',
				'fulfillments.trackings',
			)
		)->find( $order_id );

		if ( property_exists( $order, 'errors' ) ) {
			return null;
		}

		if ( $order instanceof Order ) {
			return $order;
		}

		return null;
	}

	/**
	 * Gets single SureCart product
	 *
	 * @param string $product_id
	 *
	 * @return ?Product
	 */
	public function get_single_product( string $product_id ): ?Product {
		$product = Product::with(
			array(
				'prices',
				'variants',
				'product_collections',
			)
		)->find( $product_id );

		if ( property_exists( $product, 'errors' ) ) {
			return null;
		}

		if ( $product instanceof Product ) {
			return $product;
		}

		return null;
	}

	/**
	 * Gets SureCart refund
	 *
	 * @param string $order_id
	 *
	 * @return ?Refund
	 */
	public function get_refund_items( string $order_id ): ?Refund {
		$refund = Refund::with(
			array(
				'refund.refund_items',
				'charge',
				'charge.checkout',
				'charge.checkout.order',
			)
		)->find( $order_id );

		if ( property_exists( $refund, 'errors' ) ) {
			return null;
		}

		if ( $refund instanceof Refund ) {
			return $refund;
		}

		return null;
	}

	/**
	 * Gets SureCart discount
	 *
	 * @param string $id
	 *
	 * @return ?Discount
	 */
	public function get_discount( string $id ): ?Discount {
		$discount = Discount::with( array( 'promotion' ) )->find( $id );

		if ( property_exists( $discount, 'errors' ) ) {
			return null;
		}

		if ( $discount instanceof Discount ) {
			return $discount;
		}

		return null;
	}
}
