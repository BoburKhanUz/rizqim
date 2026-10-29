// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'write_offs.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

WriteOffs _$WriteOffsFromJson(Map<String, dynamic> json) => WriteOffs(
  uuid: json['uuid'] as String,
  batchId: (json['batch_id'] as num).toInt(),
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String,
  variantName: json['variant_name'] as String?,
  quantity: json['quantity'] as num?,
  unit: json['unit'] as String,
  reason: json['reason'] as String,
  reasonLabel: json['reason_label'] as String,
  note: json['note'] as String?,
  businessDate: json['business_date'] as String,
  shiftId: (json['shift_id'] as num?)?.toInt(),
  writtenOffBy: json['written_off_by'] == null
      ? null
      : WrittenOffBy.fromJson(json['written_off_by'] as Map<String, dynamic>),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$WriteOffsToJson(WriteOffs instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'batch_id': instance.batchId,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'variant_name': instance.variantName,
  'quantity': instance.quantity,
  'unit': instance.unit,
  'reason': instance.reason,
  'reason_label': instance.reasonLabel,
  'note': instance.note,
  'business_date': instance.businessDate,
  'shift_id': instance.shiftId,
  'written_off_by': instance.writtenOffBy,
  'created_at': instance.createdAt,
};
