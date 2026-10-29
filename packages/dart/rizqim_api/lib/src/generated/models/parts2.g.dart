// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'parts2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Parts2 _$Parts2FromJson(Map<String, dynamic> json) => Parts2(
  variantId: (json['variant_id'] as num).toInt(),
  variantUuid: json['variant_uuid'] as String,
  productId: (json['product_id'] as num).toInt(),
  name: json['name'] as String,
  variantName: json['variant_name'] as String?,
  quantity: json['quantity'] as String,
  unit: json['unit'] as String,
  label: json['label'] as String,
);

Map<String, dynamic> _$Parts2ToJson(Parts2 instance) => <String, dynamic>{
  'variant_id': instance.variantId,
  'variant_uuid': instance.variantUuid,
  'product_id': instance.productId,
  'name': instance.name,
  'variant_name': instance.variantName,
  'quantity': instance.quantity,
  'unit': instance.unit,
  'label': instance.label,
};
