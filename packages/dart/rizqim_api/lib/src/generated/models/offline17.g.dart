// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline17.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline17 _$Offline17FromJson(Map<String, dynamic> json) => Offline17(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags16.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline17ToJson(Offline17 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
