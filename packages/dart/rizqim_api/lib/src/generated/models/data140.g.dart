// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data140.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data140 _$Data140FromJson(Map<String, dynamic> json) => Data140(
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips.fromJson(e as Map<String, dynamic>))
      .toList(),
  truncated: json['truncated'] as bool,
);

Map<String, dynamic> _$Data140ToJson(Data140 instance) => <String, dynamic>{
  'tips': instance.tips,
  'truncated': instance.truncated,
};
