// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffRequestBody _$V1OwnerStaffRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerStaffRequestBody(
  name: json['name'] as String,
  role: Role.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num).toInt(),
  phone: json['phone'] as String?,
);

Map<String, dynamic> _$V1OwnerStaffRequestBodyToJson(
  V1OwnerStaffRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
  'phone': instance.phone,
};
