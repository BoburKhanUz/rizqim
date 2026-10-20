// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data10 _$Data10FromJson(Map<String, dynamic> json) => Data10(
  token: json['token'] as String,
  device: Device6.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch2.fromJson(json['branch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data10ToJson(Data10 instance) => <String, dynamic>{
  'token': instance.token,
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
};
