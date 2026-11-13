// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline25.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline25 _$Offline25FromJson(Map<String, dynamic> json) => Offline25(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags24.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline25ToJson(Offline25 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
