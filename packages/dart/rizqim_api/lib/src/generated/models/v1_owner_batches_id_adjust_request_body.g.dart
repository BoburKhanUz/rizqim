// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_batches_id_adjust_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBatchesIdAdjustRequestBody _$V1OwnerBatchesIdAdjustRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBatchesIdAdjustRequestBody(
  add: json['add'] as num?,
  quantityLeft: json['quantity_left'] as num?,
  reason: json['reason'] as String?,
);

Map<String, dynamic> _$V1OwnerBatchesIdAdjustRequestBodyToJson(
  V1OwnerBatchesIdAdjustRequestBody instance,
) => <String, dynamic>{
  'add': instance.add,
  'quantity_left': instance.quantityLeft,
  'reason': instance.reason,
};
