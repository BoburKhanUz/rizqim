// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data11 _$Data11FromJson(Map<String, dynamic> json) => Data11(
  token: json['token'] as String,
  syncKey: json['sync_key'] == null
      ? null
      : SyncKey.fromJson(json['sync_key'] as Map<String, dynamic>),
  device: Device8.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch2.fromJson(json['branch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data11ToJson(Data11 instance) => <String, dynamic>{
  'token': instance.token,
  'sync_key': instance.syncKey,
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
};
