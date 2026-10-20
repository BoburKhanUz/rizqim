// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_employees_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerEmployeesIdResponse _$DeleteV1OwnerEmployeesIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerEmployeesIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerEmployeesIdResponseToJson(
  DeleteV1OwnerEmployeesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
