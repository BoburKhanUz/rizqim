// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_terminal_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1TerminalResponse _$DeleteV1TerminalResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1TerminalResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1TerminalResponseToJson(
  DeleteV1TerminalResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
