// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'quick_items.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

QuickItems _$QuickItemsFromJson(Map<String, dynamic> json) => QuickItems(
  productId: (json['product_id'] as num).toInt(),
  variantId: (json['variant_id'] as num).toInt(),
  variantUuid: json['variant_uuid'] as String,
  name: json['name'] as String,
  variantName: json['variant_name'] as String?,
  source: Source2.fromJson(json['source'] as String),
  hallId: (json['hall_id'] as num?)?.toInt(),
  quantityPerGuest: json['quantity_per_guest'] as String?,
  sortOrder: (json['sort_order'] as num).toInt(),
);

Map<String, dynamic> _$QuickItemsToJson(QuickItems instance) =>
    <String, dynamic>{
      'product_id': instance.productId,
      'variant_id': instance.variantId,
      'variant_uuid': instance.variantUuid,
      'name': instance.name,
      'variant_name': instance.variantName,
      'source': instance.source,
      'hall_id': instance.hallId,
      'quantity_per_guest': instance.quantityPerGuest,
      'sort_order': instance.sortOrder,
    };
