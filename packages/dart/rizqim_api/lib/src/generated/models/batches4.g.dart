// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'batches4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Batches4 _$Batches4FromJson(Map<String, dynamic> json) => Batches4(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  productId: (json['product_id'] as num).toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  label: json['label'] as String?,
  quantityProduced: json['quantity_produced'] as num?,
  quantityLeft: json['quantity_left'] as num?,
  startedAt: json['started_at'] as String,
);

Map<String, dynamic> _$Batches4ToJson(Batches4 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'product_id': instance.productId,
  'product_variant_id': instance.productVariantId,
  'label': instance.label,
  'quantity_produced': instance.quantityProduced,
  'quantity_left': instance.quantityLeft,
  'started_at': instance.startedAt,
};
