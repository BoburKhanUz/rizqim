// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data116.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data116 _$Data116FromJson(Map<String, dynamic> json) => Data116(
  distributions: (json['distributions'] as List<dynamic>)
      .map((e) => Distributions2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data116ToJson(Data116 instance) => <String, dynamic>{
  'distributions': instance.distributions,
};
