// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bookings_uuid_items_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBookingsUuidItemsRequestBody _$V1PosBookingsUuidItemsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBookingsUuidItemsRequestBody(
  quantity: json['quantity'] as num,
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  productVariantUuid: json['product_variant_uuid'] as String?,
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => Modifiers7.fromJson(e as Map<String, dynamic>))
      .toList(),
  note: json['note'] as String?,
  dueAt: json['due_at'] == null
      ? null
      : DateTime.parse(json['due_at'] as String),
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosBookingsUuidItemsRequestBodyToJson(
  V1PosBookingsUuidItemsRequestBody instance,
) => <String, dynamic>{
  'product_variant_id': instance.productVariantId,
  'product_variant_uuid': instance.productVariantUuid,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
  'note': instance.note,
  'due_at': instance.dueAt?.toIso8601String(),
  'version': instance.version,
};
