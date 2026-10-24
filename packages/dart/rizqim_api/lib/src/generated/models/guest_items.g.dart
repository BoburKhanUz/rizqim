// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'guest_items.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GuestItems _$GuestItemsFromJson(Map<String, dynamic> json) => GuestItems(
  hallId: (json['hall_id'] as num?)?.toInt(),
  variantUuid: json['variant_uuid'] as String,
  quantityPerGuest: json['quantity_per_guest'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
);

Map<String, dynamic> _$GuestItemsToJson(GuestItems instance) =>
    <String, dynamic>{
      'hall_id': instance.hallId,
      'variant_uuid': instance.variantUuid,
      'quantity_per_guest': instance.quantityPerGuest,
      'sort_order': instance.sortOrder,
    };
