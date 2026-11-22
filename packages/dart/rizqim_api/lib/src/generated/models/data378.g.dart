// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data378.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data378 _$Data378FromJson(Map<String, dynamic> json) => Data378(
  device: Device26.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store4.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch22.fromJson(json['branch'] as Map<String, dynamic>),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches4.fromJson(e as Map<String, dynamic>))
      .toList(),
  appRelease: json['app_release'] == null
      ? null
      : AppRelease.fromJson(json['app_release'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data378ToJson(Data378 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
  'app_release': instance.appRelease,
};
