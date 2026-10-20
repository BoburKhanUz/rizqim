// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'broadcasting_auth_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BroadcastingAuthRequestBody _$BroadcastingAuthRequestBodyFromJson(
  Map<String, dynamic> json,
) => BroadcastingAuthRequestBody(
  socketId: json['socket_id'] as String,
  channelName: json['channel_name'] as String,
);

Map<String, dynamic> _$BroadcastingAuthRequestBodyToJson(
  BroadcastingAuthRequestBody instance,
) => <String, dynamic>{
  'socket_id': instance.socketId,
  'channel_name': instance.channelName,
};
