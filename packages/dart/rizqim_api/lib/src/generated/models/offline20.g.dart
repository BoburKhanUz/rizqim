// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline20.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline20 _$Offline20FromJson(Map<String, dynamic> json) => Offline20(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags19.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline20ToJson(Offline20 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
