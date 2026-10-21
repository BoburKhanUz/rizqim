// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffIdRequestBody _$V1OwnerStaffIdRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerStaffIdRequestBody(
  name: json['name'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  phone: json['phone'] as String?,
);

Map<String, dynamic> _$V1OwnerStaffIdRequestBodyToJson(
  V1OwnerStaffIdRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'branch_id': instance.branchId,
  'phone': instance.phone,
};
