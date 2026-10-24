// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data195.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data195 _$Data195FromJson(Map<String, dynamic> json) => Data195(
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

Map<String, dynamic> _$Data195ToJson(Data195 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
};
