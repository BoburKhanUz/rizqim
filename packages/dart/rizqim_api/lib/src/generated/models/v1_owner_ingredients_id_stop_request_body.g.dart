// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_ingredients_id_stop_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerIngredientsIdStopRequestBody
_$V1OwnerIngredientsIdStopRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerIngredientsIdStopRequestBody(
      branchId: (json['branch_id'] as num?)?.toInt(),
      reason: json['reason'] as String?,
      until: json['until'] == null
          ? null
          : DateTime.parse(json['until'] as String),
    );

Map<String, dynamic> _$V1OwnerIngredientsIdStopRequestBodyToJson(
  V1OwnerIngredientsIdStopRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'reason': instance.reason,
  'until': instance.until?.toIso8601String(),
};
