// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline12 _$Offline12FromJson(Map<String, dynamic> json) => Offline12(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline12ToJson(Offline12 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
