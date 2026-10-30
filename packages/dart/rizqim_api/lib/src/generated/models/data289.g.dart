// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data289.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data289 _$Data289FromJson(Map<String, dynamic> json) => Data289(
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

Map<String, dynamic> _$Data289ToJson(Data289 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
};
