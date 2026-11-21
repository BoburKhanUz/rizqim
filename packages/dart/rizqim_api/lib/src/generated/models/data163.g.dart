// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data163.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data163 _$Data163FromJson(Map<String, dynamic> json) => Data163(
  poolPeriod: json['pool_period'] as String,
  distributions: (json['distributions'] as List<dynamic>)
      .map((e) => Distributions2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data163ToJson(Data163 instance) => <String, dynamic>{
  'pool_period': instance.poolPeriod,
  'distributions': instance.distributions,
};
