// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items10 _$Items10FromJson(Map<String, dynamic> json) => Items10(
  id: (json['id'] as num).toInt(),
  productVariantId: (json['product_variant_id'] as num).toInt(),
  productVariantUuid: json['product_variant_uuid'] as String?,
  productName: json['product_name'] as String,
  variantName: json['variant_name'] as String?,
  unit: json['unit'] as String,
  quantity: json['quantity'] as num,
  modifiers: (json['modifiers'] as List<dynamic>)
      .map((e) => e as Map<String, dynamic>)
      .toList(),
  unitPrice: json['unit_price'] as String,
  note: json['note'] as String?,
  dueAt: json['due_at'] as String?,
);

Map<String, dynamic> _$Items10ToJson(Items10 instance) => <String, dynamic>{
  'id': instance.id,
  'product_variant_id': instance.productVariantId,
  'product_variant_uuid': instance.productVariantUuid,
  'product_name': instance.productName,
  'variant_name': instance.variantName,
  'unit': instance.unit,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
  'unit_price': instance.unitPrice,
  'note': instance.note,
  'due_at': instance.dueAt,
};
