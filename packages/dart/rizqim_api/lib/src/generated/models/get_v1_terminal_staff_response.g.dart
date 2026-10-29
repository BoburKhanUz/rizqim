// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_terminal_staff_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1TerminalStaffResponse _$GetV1TerminalStaffResponseFromJson(
  Map<String, dynamic> json,
) => GetV1TerminalStaffResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data268.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1TerminalStaffResponseToJson(
  GetV1TerminalStaffResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
