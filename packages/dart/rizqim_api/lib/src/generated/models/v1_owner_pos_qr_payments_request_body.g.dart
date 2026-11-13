// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_qr_payments_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosQrPaymentsRequestBody _$V1OwnerPosQrPaymentsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPosQrPaymentsRequestBody(
  providers: (json['providers'] as List<dynamic>?)
      ?.map((e) => QrProvider.fromJson(e as String))
      .toList(),
  defaultProvider: json['default_provider'] == null
      ? null
      : QrProvider.fromJson(json['default_provider'] as String),
  expiryMinutes: (json['expiry_minutes'] as num?)?.toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerPosQrPaymentsRequestBodyToJson(
  V1OwnerPosQrPaymentsRequestBody instance,
) => <String, dynamic>{
  'providers': instance.providers,
  'default_provider': instance.defaultProvider,
  'expiry_minutes': instance.expiryMinutes,
  'branch_id': instance.branchId,
};
