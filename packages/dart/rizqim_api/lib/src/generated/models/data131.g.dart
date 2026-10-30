// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data131.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data131 _$Data131FromJson(Map<String, dynamic> json) => Data131(
  poolPeriod: json['pool_period'] as String,
  distributions: (json['distributions'] as List<dynamic>)
      .map((e) => Distributions2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data131ToJson(Data131 instance) => <String, dynamic>{
  'pool_period': instance.poolPeriod,
  'distributions': instance.distributions,
};
