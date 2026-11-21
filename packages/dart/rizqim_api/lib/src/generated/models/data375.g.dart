// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data375.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data375 _$Data375FromJson(Map<String, dynamic> json) => Data375(
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
);

Map<String, dynamic> _$Data375ToJson(Data375 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
};
