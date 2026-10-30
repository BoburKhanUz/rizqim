// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_waitlist_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerWaitlistResponse _$GetV1OwnerWaitlistResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerWaitlistResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data165.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerWaitlistResponseToJson(
  GetV1OwnerWaitlistResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
