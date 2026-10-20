// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_employees_employee_id_transfer_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerEmployeesEmployeeIdTransferResponse
_$PostV1OwnerEmployeesEmployeeIdTransferResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerEmployeesEmployeeIdTransferResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] == null
      ? null
      : User.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerEmployeesEmployeeIdTransferResponseToJson(
  PostV1OwnerEmployeesEmployeeIdTransferResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
