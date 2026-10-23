// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline2 _$Offline2FromJson(Map<String, dynamic> json) => Offline2(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline2ToJson(Offline2 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
