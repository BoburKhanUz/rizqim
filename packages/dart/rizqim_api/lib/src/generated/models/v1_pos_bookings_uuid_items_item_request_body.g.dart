// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bookings_uuid_items_item_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBookingsUuidItemsItemRequestBody
_$V1PosBookingsUuidItemsItemRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosBookingsUuidItemsItemRequestBody(
      productVariantId: (json['product_variant_id'] as num?)?.toInt(),
      productVariantUuid: json['product_variant_uuid'] as String?,
      quantity: json['quantity'] as num?,
      modifiers: (json['modifiers'] as List<dynamic>?)
          ?.map((e) => Modifiers8.fromJson(e as Map<String, dynamic>))
          .toList(),
      note: json['note'] as String?,
      dueAt: json['due_at'] == null
          ? null
          : DateTime.parse(json['due_at'] as String),
      version: (json['version'] as num?)?.toInt(),
    );

Map<String, dynamic> _$V1PosBookingsUuidItemsItemRequestBodyToJson(
  V1PosBookingsUuidItemsItemRequestBody instance,
) => <String, dynamic>{
  'product_variant_id': instance.productVariantId,
  'product_variant_uuid': instance.productVariantUuid,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
  'note': instance.note,
  'due_at': instance.dueAt?.toIso8601String(),
  'version': instance.version,
};
