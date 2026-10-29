// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_daily_menu_rotation_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerDailyMenuRotationResponse
_$PutV1OwnerDailyMenuRotationResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerDailyMenuRotationResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data51.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerDailyMenuRotationResponseToJson(
  PutV1OwnerDailyMenuRotationResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
