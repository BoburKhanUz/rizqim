// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data42.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data42 _$Data42FromJson(Map<String, dynamic> json) => Data42(
  role: json['role'] as String,
  storeId: (json['store_id'] as num?)?.toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  branchScoped: json['branch_scoped'] as bool,
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  deviceUuid: json['device_uuid'] as String?,
  timezone: json['timezone'] as String?,
);

Map<String, dynamic> _$Data42ToJson(Data42 instance) => <String, dynamic>{
  'role': instance.role,
  'store_id': instance.storeId,
  'branch_id': instance.branchId,
  'branch_scoped': instance.branchScoped,
  'permissions': instance.permissions,
  'device_uuid': instance.deviceUuid,
  'timezone': instance.timezone,
};
