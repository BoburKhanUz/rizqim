// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_start_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidStartRequestBody _$V1PosBanquetsUuidStartRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBanquetsUuidStartRequestBody(
  version: (json['version'] as num?)?.toInt(),
  waiterId: (json['waiter_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosBanquetsUuidStartRequestBodyToJson(
  V1PosBanquetsUuidStartRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'waiter_id': instance.waiterId,
};
