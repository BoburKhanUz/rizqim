// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_stop_list_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStopListRequestBody _$V1OwnerStopListRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerStopListRequestBody(
  type: Type7.fromJson(json['type'] as String),
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  reason: json['reason'] as String?,
  until: json['until'] == null ? null : DateTime.parse(json['until'] as String),
);

Map<String, dynamic> _$V1OwnerStopListRequestBodyToJson(
  V1OwnerStopListRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'type': instance.type,
  'id': instance.id,
  'reason': instance.reason,
  'until': instance.until?.toIso8601String(),
};
