// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device25.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device25 _$Device25FromJson(Map<String, dynamic> json) => Device25(
  uuid: json['uuid'] as String,
  type: json['type'] as String,
  name: json['name'] as String,
  platform: json['platform'] as String?,
  appVersion: json['app_version'] as String?,
  storeId: (json['store_id'] as num?)?.toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  user: json['user'] == null
      ? null
      : User25.fromJson(json['user'] as Map<String, dynamic>),
  hasPushToken: json['has_push_token'] as bool,
  lastSeenAt: json['last_seen_at'] as String?,
  revokedAt: json['revoked_at'] as String?,
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Device25ToJson(Device25 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'name': instance.name,
  'platform': instance.platform,
  'app_version': instance.appVersion,
  'store_id': instance.storeId,
  'branch_id': instance.branchId,
  'user': instance.user,
  'has_push_token': instance.hasPushToken,
  'last_seen_at': instance.lastSeenAt,
  'revoked_at': instance.revokedAt,
  'created_at': instance.createdAt,
};
