// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_settle_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidSettleRequestBody _$V1PosBanquetsUuidSettleRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBanquetsUuidSettleRequestBody(
  shiftUuid: json['shift_uuid'] as String?,
  version: (json['version'] as num?)?.toInt(),
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosBanquetsUuidSettleRequestBodyToJson(
  V1PosBanquetsUuidSettleRequestBody instance,
) => <String, dynamic>{
  'shift_uuid': instance.shiftUuid,
  'version': instance.version,
  'approval_token': instance.approvalToken,
};
