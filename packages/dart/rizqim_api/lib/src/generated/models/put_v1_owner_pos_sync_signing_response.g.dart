// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_pos_sync_signing_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPosSyncSigningResponse _$PutV1OwnerPosSyncSigningResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerPosSyncSigningResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data162.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerPosSyncSigningResponseToJson(
  PutV1OwnerPosSyncSigningResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
