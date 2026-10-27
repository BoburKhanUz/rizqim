// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'session2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Session2 _$Session2FromJson(Map<String, dynamic> json) => Session2(
  id: (json['id'] as num).toInt(),
  ip: json['ip'] as String?,
  userAgent: json['user_agent'] as String?,
  createdAt: json['created_at'] as String?,
  lastUsedAt: json['last_used_at'] as String?,
  expiresAt: json['expires_at'] as String?,
  reauthenticatedUntil: json['reauthenticated_until'] as String?,
  current: json['current'] as bool,
);

Map<String, dynamic> _$Session2ToJson(Session2 instance) => <String, dynamic>{
  'id': instance.id,
  'ip': instance.ip,
  'user_agent': instance.userAgent,
  'created_at': instance.createdAt,
  'last_used_at': instance.lastUsedAt,
  'expires_at': instance.expiresAt,
  'reauthenticated_until': instance.reauthenticatedUntil,
  'current': instance.current,
};
