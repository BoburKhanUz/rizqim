// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data216.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data216 _$Data216FromJson(Map<String, dynamic> json) => Data216(
  device: Device25.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store4.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch13.fromJson(json['branch'] as Map<String, dynamic>),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data216ToJson(Data216 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
};
