// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_sync_signing_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosSyncSigningResponse _$GetV1OwnerPosSyncSigningResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosSyncSigningResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data159.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosSyncSigningResponseToJson(
  GetV1OwnerPosSyncSigningResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
