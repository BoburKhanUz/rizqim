// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_channel_commissions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerChannelCommissionsResponse
_$PutV1OwnerChannelCommissionsResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerChannelCommissionsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data61.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerChannelCommissionsResponseToJson(
  PutV1OwnerChannelCommissionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
