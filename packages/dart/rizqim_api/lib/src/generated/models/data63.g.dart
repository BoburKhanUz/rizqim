// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data63.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data63 _$Data63FromJson(Map<String, dynamic> json) => Data63(
  device: Device9.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store2.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch3.fromJson(json['branch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data63ToJson(Data63 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
};
