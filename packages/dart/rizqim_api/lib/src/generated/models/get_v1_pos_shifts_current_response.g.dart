// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_shifts_current_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosShiftsCurrentResponse _$GetV1PosShiftsCurrentResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosShiftsCurrentResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data194.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosShiftsCurrentResponseToJson(
  GetV1PosShiftsCurrentResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
