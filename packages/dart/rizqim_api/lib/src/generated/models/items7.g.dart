// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items7 _$Items7FromJson(Map<String, dynamic> json) => Items7(
  id: (json['id'] as num).toInt(),
  productId: (json['product_id'] as num?)?.toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  name: json['name'] as String,
  quantityPerPerson: json['quantity_per_person'] as String,
  unit: json['unit'] as String,
);

Map<String, dynamic> _$Items7ToJson(Items7 instance) => <String, dynamic>{
  'id': instance.id,
  'product_id': instance.productId,
  'product_variant_id': instance.productVariantId,
  'name': instance.name,
  'quantity_per_person': instance.quantityPerPerson,
  'unit': instance.unit,
};
