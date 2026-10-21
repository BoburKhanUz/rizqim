// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data94.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data94 _$Data94FromJson(Map<String, dynamic> json) => Data94(
  device: Device10.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store2.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch11.fromJson(json['branch'] as Map<String, dynamic>),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data94ToJson(Data94 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
};
