// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_daily_menu_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerDailyMenuResponse _$PutV1OwnerDailyMenuResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerDailyMenuResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data51.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerDailyMenuResponseToJson(
  PutV1OwnerDailyMenuResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
