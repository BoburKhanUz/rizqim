// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data290.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data290 _$Data290FromJson(Map<String, dynamic> json) => Data290(
  device: Device25.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store4.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch16.fromJson(json['branch'] as Map<String, dynamic>),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data290ToJson(Data290 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
};
