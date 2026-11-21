// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_terminal_sync_key_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1TerminalSyncKeyResponse _$PostV1TerminalSyncKeyResponseFromJson(
  Map<String, dynamic> json,
) => PostV1TerminalSyncKeyResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data378.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1TerminalSyncKeyResponseToJson(
  PostV1TerminalSyncKeyResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
