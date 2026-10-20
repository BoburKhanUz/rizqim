// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Order _$OrderFromJson(Map<String, dynamic> json) => Order(
  deletedAt: json['deleted_at'] == null
      ? null
      : DateTime.parse(json['deleted_at'] as String),
  uuid: json['uuid'] as String,
  shortCode: json['short_code'] as String?,
  storeId: (json['store_id'] as num).toInt(),
  userId: (json['user_id'] as num).toInt(),
  status: OrderStatus.fromJson(json['status'] as String),
  paymentStatus: PaymentStatus.fromJson(json['payment_status'] as String),
  orderType: OrderType.fromJson(json['order_type'] as String),
  subtotal: json['subtotal'] as String,
  deliveryPrice: json['delivery_price'] as String,
  totalPrice: json['total_price'] as String,
  currency: json['currency'] as String,
  notes: json['notes'] as String?,
  shippingAddress: json['shipping_address'] as Map<String, dynamic>?,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  id: (json['id'] as num).toInt(),
  paymentMethod: json['payment_method'] as String,
  courierId: (json['courier_id'] as num?)?.toInt(),
  commissionAmount: json['commission_amount'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
  addressId: (json['address_id'] as num?)?.toInt(),
  managerId: (json['manager_id'] as num?)?.toInt(),
  paymentOwner: json['payment_owner'] as String,
  discount: json['discount'] as String,
  promoCodeId: (json['promo_code_id'] as num?)?.toInt(),
  settlementFailedAt: json['settlement_failed_at'] == null
      ? null
      : DateTime.parse(json['settlement_failed_at'] as String),
  settlementError: json['settlement_error'] as String?,
  settlementAttempts: (json['settlement_attempts'] as num).toInt(),
  cancelReason: json['cancel_reason'] == null
      ? null
      : CancelReason.fromJson(json['cancel_reason'] as String),
  cancelNote: json['cancel_note'] as String?,
  cancelledBy: json['cancelled_by'],
  address: json['address'] as String?,
  externalId: json['external_id'] as String?,
  serviceChargeAmount: json['service_charge_amount'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  guestsCount: (json['guests_count'] as num?)?.toInt(),
  hallRef: json['hall_ref'] as String?,
  tableRef: json['table_ref'] as String?,
  type: json['type'] as String,
  channel: OrderChannel.fromJson(json['channel'] as String),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  user: json['user'] == null
      ? null
      : User.fromJson(json['user'] as Map<String, dynamic>),
  courier: json['courier'] == null
      ? null
      : User.fromJson(json['courier'] as Map<String, dynamic>),
  manager: json['manager'] == null
      ? null
      : User.fromJson(json['manager'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch.fromJson(json['branch'] as Map<String, dynamic>),
  items: (json['items'] as List<dynamic>?)
      ?.map((e) => OrderItem.fromJson(e as Map<String, dynamic>))
      .toList(),
  statusHistories: (json['status_histories'] as List<dynamic>?)
      ?.map((e) => OrderStatusHistory.fromJson(e as Map<String, dynamic>))
      .toList(),
  payments: (json['payments'] as List<dynamic>?)
      ?.map((e) => Payment.fromJson(e as Map<String, dynamic>))
      .toList(),
  promoCode: json['promo_code'] == null
      ? null
      : PromoCode.fromJson(json['promo_code'] as Map<String, dynamic>),
);

Map<String, dynamic> _$OrderToJson(Order instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'short_code': instance.shortCode,
  'store_id': instance.storeId,
  'user_id': instance.userId,
  'status': instance.status,
  'payment_status': instance.paymentStatus,
  'order_type': instance.orderType,
  'subtotal': instance.subtotal,
  'delivery_price': instance.deliveryPrice,
  'total_price': instance.totalPrice,
  'currency': instance.currency,
  'notes': instance.notes,
  'shipping_address': instance.shippingAddress,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'deleted_at': instance.deletedAt?.toIso8601String(),
  'payment_method': instance.paymentMethod,
  'courier_id': instance.courierId,
  'commission_amount': instance.commissionAmount,
  'branch_id': instance.branchId,
  'address_id': instance.addressId,
  'manager_id': instance.managerId,
  'payment_owner': instance.paymentOwner,
  'discount': instance.discount,
  'promo_code_id': instance.promoCodeId,
  'settlement_failed_at': instance.settlementFailedAt?.toIso8601String(),
  'settlement_error': instance.settlementError,
  'settlement_attempts': instance.settlementAttempts,
  'cancel_reason': instance.cancelReason,
  'cancel_note': instance.cancelNote,
  'cancelled_by': instance.cancelledBy,
  'channel': instance.channel,
  'external_id': instance.externalId,
  'service_charge_amount': instance.serviceChargeAmount,
  'place_fee_amount': instance.placeFeeAmount,
  'guests_count': instance.guestsCount,
  'hall_ref': instance.hallRef,
  'table_ref': instance.tableRef,
  'type': instance.type,
  'address': instance.address,
  'store': instance.store,
  'user': instance.user,
  'courier': instance.courier,
  'manager': instance.manager,
  'branch': instance.branch,
  'items': instance.items,
  'status_histories': instance.statusHistories,
  'payments': instance.payments,
  'promo_code': instance.promoCode,
};
