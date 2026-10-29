// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'batches2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Batches2 _$Batches2FromJson(Map<String, dynamic> json) => Batches2(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  businessDate: json['business_date'] as String,
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String?,
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  variantName: json['variant_name'] as String?,
  unit: json['unit'] as String,
  label: json['label'] as String,
  startedAt: json['started_at'] as String,
  closedAt: json['closed_at'] as String?,
  isOpen: json['is_open'] as bool,
  produced: json['produced'] as num,
  sold: json['sold'] as num,
  writtenOff: json['written_off'] as num,
  left: json['left'] as num,
  oversold: json['oversold'] as num,
  variance: json['variance'] as num,
  soldPercent: json['sold_percent'] as String?,
  writtenOffPercent: json['written_off_percent'] as String?,
);

Map<String, dynamic> _$Batches2ToJson(Batches2 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'business_date': instance.businessDate,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'product_variant_id': instance.productVariantId,
  'variant_name': instance.variantName,
  'unit': instance.unit,
  'label': instance.label,
  'started_at': instance.startedAt,
  'closed_at': instance.closedAt,
  'is_open': instance.isOpen,
  'produced': instance.produced,
  'sold': instance.sold,
  'written_off': instance.writtenOff,
  'left': instance.left,
  'oversold': instance.oversold,
  'variance': instance.variance,
  'sold_percent': instance.soldPercent,
  'written_off_percent': instance.writtenOffPercent,
};
