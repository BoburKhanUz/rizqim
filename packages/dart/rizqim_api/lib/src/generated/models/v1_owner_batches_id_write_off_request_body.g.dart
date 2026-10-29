// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_batches_id_write_off_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBatchesIdWriteOffRequestBody
_$V1OwnerBatchesIdWriteOffRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerBatchesIdWriteOffRequestBody(
      quantity: json['quantity'] as num,
      reason: Reason3.fromJson(json['reason'] as String),
      note: json['note'] as String?,
      close: json['close'] as bool?,
    );

Map<String, dynamic> _$V1OwnerBatchesIdWriteOffRequestBodyToJson(
  V1OwnerBatchesIdWriteOffRequestBody instance,
) => <String, dynamic>{
  'quantity': instance.quantity,
  'reason': instance.reason,
  'note': instance.note,
  'close': instance.close,
};
