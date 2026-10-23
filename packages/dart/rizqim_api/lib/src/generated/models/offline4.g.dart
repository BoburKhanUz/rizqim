// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline4 _$Offline4FromJson(Map<String, dynamic> json) => Offline4(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline4ToJson(Offline4 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
