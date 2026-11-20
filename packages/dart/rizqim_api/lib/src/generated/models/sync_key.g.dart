// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sync_key.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

SyncKey _$SyncKeyFromJson(Map<String, dynamic> json) =>
    SyncKey(key: json['key'] as String, kid: (json['kid'] as num).toInt());

Map<String, dynamic> _$SyncKeyToJson(SyncKey instance) => <String, dynamic>{
  'key': instance.key,
  'kid': instance.kid,
};
