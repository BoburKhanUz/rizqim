// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'options3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Options3 _$Options3FromJson(Map<String, dynamic> json) => Options3(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  variantId: (json['variant_id'] as num).toInt(),
  variantUuid: json['variant_uuid'] as String,
  productId: (json['product_id'] as num?)?.toInt(),
  name: json['name'] as String,
  variantName: json['variant_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
  modifiers: (json['modifiers'] as List<dynamic>)
      .map((e) => Modifiers3.fromJson(e as Map<String, dynamic>))
      .toList(),
  isDefault: json['is_default'] as bool,
  label: json['label'] as String?,
  receiptLabel: json['receipt_label'] as String,
);

Map<String, dynamic> _$Options3ToJson(Options3 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'variant_id': instance.variantId,
  'variant_uuid': instance.variantUuid,
  'product_id': instance.productId,
  'name': instance.name,
  'variant_name': instance.variantName,
  'quantity': instance.quantity,
  'price_delta': instance.priceDelta,
  'modifiers': instance.modifiers,
  'is_default': instance.isDefault,
  'label': instance.label,
  'receipt_label': instance.receiptLabel,
};
