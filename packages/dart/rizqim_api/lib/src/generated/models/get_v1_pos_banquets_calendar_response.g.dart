// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_calendar_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsCalendarResponse _$GetV1PosBanquetsCalendarResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBanquetsCalendarResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data220.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBanquetsCalendarResponseToJson(
  GetV1PosBanquetsCalendarResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
