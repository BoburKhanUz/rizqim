// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data370.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data370 _$Data370FromJson(Map<String, dynamic> json) => Data370(
  device: Device25.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store4.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch22.fromJson(json['branch'] as Map<String, dynamic>),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data370ToJson(Data370 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
};
