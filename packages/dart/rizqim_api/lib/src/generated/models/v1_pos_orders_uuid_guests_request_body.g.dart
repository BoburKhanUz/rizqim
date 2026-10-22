// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_guests_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidGuestsRequestBody _$V1PosOrdersUuidGuestsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidGuestsRequestBody(
  version: (json['version'] as num).toInt(),
  guestsCount: (json['guests_count'] as num).toInt(),
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidGuestsRequestBodyToJson(
  V1PosOrdersUuidGuestsRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'guests_count': instance.guestsCount,
  'approval_token': instance.approvalToken,
};
