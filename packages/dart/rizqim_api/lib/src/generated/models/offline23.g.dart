// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline23.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline23 _$Offline23FromJson(Map<String, dynamic> json) => Offline23(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags22.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline23ToJson(Offline23 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
