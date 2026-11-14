// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_banquets_payment_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosBanquetsPaymentSettingsResponse
_$GetV1OwnerPosBanquetsPaymentSettingsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosBanquetsPaymentSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data144.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosBanquetsPaymentSettingsResponseToJson(
  GetV1OwnerPosBanquetsPaymentSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
