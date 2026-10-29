// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_batch_plans_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBatchPlansRequestBody _$V1OwnerBatchPlansRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBatchPlansRequestBody(
  productId: (json['product_id'] as num).toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  time: json['time'] as String?,
  plannedAt: json['planned_at'] == null
      ? null
      : DateTime.parse(json['planned_at'] as String),
  quantity: json['quantity'] as num?,
  label: json['label'] as String?,
);

Map<String, dynamic> _$V1OwnerBatchPlansRequestBodyToJson(
  V1OwnerBatchPlansRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'product_id': instance.productId,
  'product_variant_id': instance.productVariantId,
  'time': instance.time,
  'planned_at': instance.plannedAt?.toIso8601String(),
  'quantity': instance.quantity,
  'label': instance.label,
};
