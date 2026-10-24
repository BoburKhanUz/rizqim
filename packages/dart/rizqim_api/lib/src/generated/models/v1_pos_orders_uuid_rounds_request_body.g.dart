// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_rounds_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidRoundsRequestBody _$V1PosOrdersUuidRoundsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidRoundsRequestBody(
  version: (json['version'] as num).toInt(),
  items: (json['items'] as List<dynamic>)
      .map((e) => Items40.fromJson(e as Map<String, dynamic>))
      .toList(),
  fireMode: json['fire_mode'] == null
      ? null
      : FireMode.fromJson(json['fire_mode'] as String?),
  send: json['send'] as bool?,
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidRoundsRequestBodyToJson(
  V1PosOrdersUuidRoundsRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'items': instance.items,
  'fire_mode': instance.fireMode,
  'send': instance.send,
  'approval_token': instance.approvalToken,
};
