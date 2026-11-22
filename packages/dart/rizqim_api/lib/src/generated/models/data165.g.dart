// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data165.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data165 _$Data165FromJson(Map<String, dynamic> json) => Data165(
  poolPeriod: json['pool_period'] as String,
  distributions: (json['distributions'] as List<dynamic>)
      .map((e) => Distributions2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data165ToJson(Data165 instance) => <String, dynamic>{
  'pool_period': instance.poolPeriod,
  'distributions': instance.distributions,
};
