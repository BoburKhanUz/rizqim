// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline15.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline15 _$Offline15FromJson(Map<String, dynamic> json) => Offline15(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags14.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline15ToJson(Offline15 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
