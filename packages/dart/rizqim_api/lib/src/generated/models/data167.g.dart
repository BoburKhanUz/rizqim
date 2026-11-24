// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data167.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data167 _$Data167FromJson(Map<String, dynamic> json) => Data167(
  poolPeriod: json['pool_period'] as String,
  distributions: (json['distributions'] as List<dynamic>)
      .map((e) => Distributions2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data167ToJson(Data167 instance) => <String, dynamic>{
  'pool_period': instance.poolPeriod,
  'distributions': instance.distributions,
};
