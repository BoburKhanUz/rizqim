// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_staff_pay_work_sessions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerStaffPayWorkSessionsResponse
_$GetV1OwnerStaffPayWorkSessionsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerStaffPayWorkSessionsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: (json['data'] as List<dynamic>)
          .map((e) => Data172.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$GetV1OwnerStaffPayWorkSessionsResponseToJson(
  GetV1OwnerStaffPayWorkSessionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
