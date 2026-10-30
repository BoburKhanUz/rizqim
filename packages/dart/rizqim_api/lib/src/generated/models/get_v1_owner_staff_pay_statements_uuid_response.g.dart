// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_staff_pay_statements_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerStaffPayStatementsUuidResponse
_$GetV1OwnerStaffPayStatementsUuidResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerStaffPayStatementsUuidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data166.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerStaffPayStatementsUuidResponseToJson(
  GetV1OwnerStaffPayStatementsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
