// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data192.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data192 _$Data192FromJson(Map<String, dynamic> json) => Data192(
  device: Device24.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store3.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch13.fromJson(json['branch'] as Map<String, dynamic>),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data192ToJson(Data192 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
};
