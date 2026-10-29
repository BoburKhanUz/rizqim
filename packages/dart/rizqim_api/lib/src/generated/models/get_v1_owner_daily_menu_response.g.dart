// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_daily_menu_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerDailyMenuResponse _$GetV1OwnerDailyMenuResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerDailyMenuResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data48.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerDailyMenuResponseToJson(
  GetV1OwnerDailyMenuResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
