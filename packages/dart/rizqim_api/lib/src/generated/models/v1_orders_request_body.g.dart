// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_orders_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OrdersRequestBody _$V1OrdersRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OrdersRequestBody(
      storeId: (json['store_id'] as num).toInt(),
      items: (json['items'] as List<dynamic>)
          .map((e) => Items2.fromJson(e as Map<String, dynamic>))
          .toList(),
      type: Type4.fromJson(json['type'] as String),
      paymentMethod: PaymentMethod.fromJson(json['payment_method'] as String),
      channel: json['channel'] == null
          ? null
          : Channel2.fromJson(json['channel'] as String?),
      branchId: (json['branch_id'] as num?)?.toInt(),
      address: json['address'] as String?,
      latitude: json['latitude'] as num?,
      longitude: json['longitude'] as num?,
      notes: json['notes'] as String?,
      promoCode: json['promo_code'] as String?,
      pickupAt: json['pickup_at'] == null
          ? null
          : DateTime.parse(json['pickup_at'] as String),
    );

Map<String, dynamic> _$V1OrdersRequestBodyToJson(
  V1OrdersRequestBody instance,
) => <String, dynamic>{
  'store_id': instance.storeId,
  'items': instance.items,
  'channel': instance.channel,
  'type': instance.type,
  'payment_method': instance.paymentMethod,
  'branch_id': instance.branchId,
  'address': instance.address,
  'latitude': instance.latitude,
  'longitude': instance.longitude,
  'notes': instance.notes,
  'promo_code': instance.promoCode,
  'pickup_at': instance.pickupAt?.toIso8601String(),
};
