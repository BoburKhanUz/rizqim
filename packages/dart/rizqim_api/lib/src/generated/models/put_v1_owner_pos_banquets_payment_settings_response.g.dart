// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_pos_banquets_payment_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPosBanquetsPaymentSettingsResponse
_$PutV1OwnerPosBanquetsPaymentSettingsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerPosBanquetsPaymentSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data145.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerPosBanquetsPaymentSettingsResponseToJson(
  PutV1OwnerPosBanquetsPaymentSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
