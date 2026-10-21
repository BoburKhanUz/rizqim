// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_approvals_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosApprovalsRequestBody _$V1PosApprovalsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosApprovalsRequestBody(
  permission: Permission.fromJson(json['permission'] as String),
  pin: json['pin'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  staffCode: json['staff_code'] as String?,
);

Map<String, dynamic> _$V1PosApprovalsRequestBodyToJson(
  V1PosApprovalsRequestBody instance,
) => <String, dynamic>{
  'permission': instance.permission,
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin': instance.pin,
};
