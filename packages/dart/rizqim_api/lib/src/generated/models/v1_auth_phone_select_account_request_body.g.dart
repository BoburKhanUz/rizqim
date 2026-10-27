// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_auth_phone_select_account_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AuthPhoneSelectAccountRequestBody
_$V1AuthPhoneSelectAccountRequestBodyFromJson(Map<String, dynamic> json) =>
    V1AuthPhoneSelectAccountRequestBody(
      device: Device3.fromJson(json['device'] as Map<String, dynamic>),
      selectionToken: json['selection_token'] as String,
      userId: (json['user_id'] as num).toInt(),
    );

Map<String, dynamic> _$V1AuthPhoneSelectAccountRequestBodyToJson(
  V1AuthPhoneSelectAccountRequestBody instance,
) => <String, dynamic>{
  'device': instance.device,
  'selection_token': instance.selectionToken,
  'user_id': instance.userId,
};
