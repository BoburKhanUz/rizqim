// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_guests_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidGuestsRequestBody _$V1PosBanquetsUuidGuestsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBanquetsUuidGuestsRequestBody(
  guestsActual: (json['guests_actual'] as num).toInt(),
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosBanquetsUuidGuestsRequestBodyToJson(
  V1PosBanquetsUuidGuestsRequestBody instance,
) => <String, dynamic>{
  'guests_actual': instance.guestsActual,
  'version': instance.version,
};
