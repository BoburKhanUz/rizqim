// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_unfreeze_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidUnfreezeRequestBody
_$V1PosBanquetsUuidUnfreezeRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosBanquetsUuidUnfreezeRequestBody(
      reason: json['reason'] as String,
      version: (json['version'] as num?)?.toInt(),
    );

Map<String, dynamic> _$V1PosBanquetsUuidUnfreezeRequestBodyToJson(
  V1PosBanquetsUuidUnfreezeRequestBody instance,
) => <String, dynamic>{'reason': instance.reason, 'version': instance.version};
