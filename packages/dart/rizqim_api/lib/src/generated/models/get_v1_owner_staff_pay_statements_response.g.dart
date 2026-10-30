// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_staff_pay_statements_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerStaffPayStatementsResponse
_$GetV1OwnerStaffPayStatementsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerStaffPayStatementsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: (json['data'] as List<dynamic>)
          .map((e) => Data164.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$GetV1OwnerStaffPayStatementsResponseToJson(
  GetV1OwnerStaffPayStatementsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
