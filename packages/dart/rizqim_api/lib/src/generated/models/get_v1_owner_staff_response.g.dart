// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_staff_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerStaffResponse _$GetV1OwnerStaffResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerStaffResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data71.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerStaffResponseToJson(
  GetV1OwnerStaffResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
