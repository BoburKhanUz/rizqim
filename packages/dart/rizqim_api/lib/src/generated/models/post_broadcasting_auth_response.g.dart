// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_broadcasting_auth_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostBroadcastingAuthResponse _$PostBroadcastingAuthResponseFromJson(
  Map<String, dynamic> json,
) => PostBroadcastingAuthResponse(
  auth: json['auth'] as String,
  channelData: json['channel_data'] as String?,
);

Map<String, dynamic> _$PostBroadcastingAuthResponseToJson(
  PostBroadcastingAuthResponse instance,
) => <String, dynamic>{
  'auth': instance.auth,
  'channel_data': instance.channelData,
};
