// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data132.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data132 _$Data132FromJson(Map<String, dynamic> json) => Data132(
  poolPeriod: json['pool_period'] as String,
  distributions: (json['distributions'] as List<dynamic>)
      .map((e) => Distributions2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data132ToJson(Data132 instance) => <String, dynamic>{
  'pool_period': instance.poolPeriod,
  'distributions': instance.distributions,
};
