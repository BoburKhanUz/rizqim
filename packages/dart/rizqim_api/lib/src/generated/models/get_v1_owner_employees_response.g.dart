// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_employees_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerEmployeesResponse _$GetV1OwnerEmployeesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerEmployeesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data67.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerEmployeesResponseToJson(
  GetV1OwnerEmployeesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
