// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch.dart';
import 'cancel_reason.dart';
import 'delivery_zone.dart';
import 'order_channel.dart';
import 'order_item.dart';
import 'order_status.dart';
import 'order_status_history.dart';
import 'order_type.dart';
import 'payment.dart';
import 'payment_status.dart';
import 'promo_code.dart';
import 'store.dart';
import 'user.dart';

part 'order.g.dart';

@JsonSerializable()
class Order {
  const Order({
    required this.courierId,
    required this.uuid,
    required this.shortCode,
    required this.storeId,
    required this.userId,
    required this.status,
    required this.paymentStatus,
    required this.orderType,
    required this.subtotal,
    required this.deliveryPrice,
    required this.totalPrice,
    required this.currency,
    required this.notes,
    required this.shippingAddress,
    required this.createdAt,
    required this.updatedAt,
    required this.deletedAt,
    required this.paymentMethod,
    required this.id,
    required this.commissionAmount,
    required this.branchId,
    required this.addressId,
    required this.managerId,
    required this.paymentOwner,
    required this.discount,
    required this.promoCodeId,
    required this.settlementFailedAt,
    required this.settlementError,
    required this.settlementAttempts,
    required this.cancelReason,
    required this.cancelNote,
    required this.cancelledBy,
    required this.channel,
    required this.externalId,
    required this.serviceChargeAmount,
    required this.placeFeeAmount,
    required this.address,
    required this.hallRef,
    required this.tableRef,
    required this.deliveryZoneId,
    required this.deliveryZoneName,
    required this.deliveryZonePrice,
    required this.deliveryMinOrderAmount,
    required this.type,
    required this.guestsCount,
    this.store,
    this.user,
    this.courier,
    this.manager,
    this.branch,
    this.deliveryZone,
    this.items,
    this.statusHistories,
    this.payments,
    this.promoCode,
  });

  factory Order.fromJson(Map<String, Object?> json) => _$OrderFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'short_code')
  final String? shortCode;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'user_id')
  final int userId;
  final OrderStatus status;
  @JsonKey(name: 'payment_status')
  final PaymentStatus paymentStatus;
  @JsonKey(name: 'order_type')
  final OrderType orderType;
  final String subtotal;
  @JsonKey(name: 'delivery_price')
  final String deliveryPrice;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  final String currency;
  final String? notes;
  @JsonKey(name: 'shipping_address')
  final Map<String, dynamic>? shippingAddress;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'deleted_at')
  final DateTime? deletedAt;
  @JsonKey(name: 'payment_method')
  final String paymentMethod;
  @JsonKey(name: 'courier_id')
  final int? courierId;
  @JsonKey(name: 'commission_amount')
  final String commissionAmount;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'address_id')
  final int? addressId;
  @JsonKey(name: 'manager_id')
  final int? managerId;
  @JsonKey(name: 'payment_owner')
  final String paymentOwner;
  final String discount;
  @JsonKey(name: 'promo_code_id')
  final int? promoCodeId;
  @JsonKey(name: 'settlement_failed_at')
  final DateTime? settlementFailedAt;
  @JsonKey(name: 'settlement_error')
  final String? settlementError;
  @JsonKey(name: 'settlement_attempts')
  final int settlementAttempts;
  @JsonKey(name: 'cancel_reason')
  final CancelReason? cancelReason;
  @JsonKey(name: 'cancel_note')
  final String? cancelNote;
  @JsonKey(name: 'cancelled_by')
  final dynamic cancelledBy;
  final OrderChannel channel;
  @JsonKey(name: 'external_id')
  final String? externalId;
  @JsonKey(name: 'service_charge_amount')
  final String serviceChargeAmount;
  @JsonKey(name: 'place_fee_amount')
  final String placeFeeAmount;
  @JsonKey(name: 'guests_count')
  final int? guestsCount;
  @JsonKey(name: 'hall_ref')
  final String? hallRef;
  @JsonKey(name: 'table_ref')
  final String? tableRef;
  @JsonKey(name: 'delivery_zone_id')
  final int? deliveryZoneId;
  @JsonKey(name: 'delivery_zone_name')
  final String? deliveryZoneName;
  @JsonKey(name: 'delivery_zone_price')
  final String? deliveryZonePrice;
  @JsonKey(name: 'delivery_min_order_amount')
  final String? deliveryMinOrderAmount;
  final String type;
  final String? address;
  final Store? store;
  final User? user;
  final User? courier;
  final User? manager;
  final Branch? branch;
  @JsonKey(name: 'delivery_zone')
  final DeliveryZone? deliveryZone;
  final List<OrderItem>? items;
  @JsonKey(name: 'status_histories')
  final List<OrderStatusHistory>? statusHistories;
  final List<Payment>? payments;
  @JsonKey(name: 'promo_code')
  final PromoCode? promoCode;

  Map<String, Object?> toJson() => _$OrderToJson(this);
}
