// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'batch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Batch _$BatchFromJson(Map<String, dynamic> json) => Batch(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String,
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  variantName: json['variant_name'] as String?,
  unit: json['unit'] as String?,
  label: json['label'] as String?,
  quantityProduced: json['quantity_produced'] as num?,
  quantityLeft: json['quantity_left'] as num?,
  writtenOffQuantity: json['written_off_quantity'] as num?,
  isOpen: json['is_open'] as bool,
  startedAt: json['started_at'] as String?,
  soldOutAt: json['sold_out_at'] as String?,
  closedAt: json['closed_at'] as String?,
);

Map<String, dynamic> _$BatchToJson(Batch instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'branch_id': instance.branchId,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'product_variant_id': instance.productVariantId,
  'variant_name': instance.variantName,
  'unit': instance.unit,
  'label': instance.label,
  'quantity_produced': instance.quantityProduced,
  'quantity_left': instance.quantityLeft,
  'written_off_quantity': instance.writtenOffQuantity,
  'is_open': instance.isOpen,
  'started_at': instance.startedAt,
  'sold_out_at': instance.soldOutAt,
  'closed_at': instance.closedAt,
};
