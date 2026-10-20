// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'promo_code.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PromoCode _$PromoCodeFromJson(Map<String, dynamic> json) => PromoCode(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  type: json['type'] as String,
  value: json['value'] as num,
  minOrderAmount: json['min_order_amount'] as String,
  maxDiscountAmount: json['max_discount_amount'] as String?,
  isActive: json['is_active'] as bool,
  expiresAt: json['expires_at'] == null
      ? null
      : DateTime.parse(json['expires_at'] as String),
  storeId: (json['store_id'] as num?)?.toInt(),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  usageLimit: (json['usage_limit'] as num?)?.toInt(),
  perUserLimit: (json['per_user_limit'] as num?)?.toInt(),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  orders: (json['orders'] as List<dynamic>?)
      ?.map((e) => Order.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$PromoCodeToJson(PromoCode instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'type': instance.type,
  'value': instance.value,
  'min_order_amount': instance.minOrderAmount,
  'max_discount_amount': instance.maxDiscountAmount,
  'is_active': instance.isActive,
  'expires_at': instance.expiresAt?.toIso8601String(),
  'store_id': instance.storeId,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'usage_limit': instance.usageLimit,
  'per_user_limit': instance.perUserLimit,
  'store': instance.store,
  'orders': instance.orders,
};
