// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_terminal_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1TerminalResponse _$GetV1TerminalResponseFromJson(
  Map<String, dynamic> json,
) => GetV1TerminalResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data195.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1TerminalResponseToJson(
  GetV1TerminalResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
