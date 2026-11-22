// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data166.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data166 _$Data166FromJson(Map<String, dynamic> json) => Data166(
  poolPeriod: json['pool_period'] as String,
  distributions: (json['distributions'] as List<dynamic>)
      .map((e) => Distributions2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data166ToJson(Data166 instance) => <String, dynamic>{
  'pool_period': instance.poolPeriod,
  'distributions': instance.distributions,
};
