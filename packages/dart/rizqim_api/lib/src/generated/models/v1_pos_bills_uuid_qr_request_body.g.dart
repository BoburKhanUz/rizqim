// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bills_uuid_qr_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBillsUuidQrRequestBody _$V1PosBillsUuidQrRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBillsUuidQrRequestBody(
  provider: json['provider'] == null
      ? null
      : QrProvider.fromJson(json['provider'] as String),
  amount: json['amount'] as num?,
  shiftUuid: json['shift_uuid'] as String?,
);

Map<String, dynamic> _$V1PosBillsUuidQrRequestBodyToJson(
  V1PosBillsUuidQrRequestBody instance,
) => <String, dynamic>{
  'provider': instance.provider,
  'amount': instance.amount,
  'shift_uuid': instance.shiftUuid,
};
