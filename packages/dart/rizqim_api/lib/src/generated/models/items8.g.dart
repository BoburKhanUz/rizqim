// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items8 _$Items8FromJson(Map<String, dynamic> json) => Items8(
  quantityPerPerson: json['quantity_per_person'] as num,
  productId: (json['product_id'] as num?)?.toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  unit: json['unit'] == null ? null : Unit2.fromJson(json['unit'] as String?),
);

Map<String, dynamic> _$Items8ToJson(Items8 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'product_variant_id': instance.productVariantId,
  'name': instance.name,
  'quantity_per_person': instance.quantityPerPerson,
  'unit': instance.unit,
};
