// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline11 _$Offline11FromJson(Map<String, dynamic> json) => Offline11(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline11ToJson(Offline11 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
