// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data138.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data138 _$Data138FromJson(Map<String, dynamic> json) => Data138(
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips.fromJson(e as Map<String, dynamic>))
      .toList(),
  truncated: json['truncated'] as bool,
);

Map<String, dynamic> _$Data138ToJson(Data138 instance) => <String, dynamic>{
  'tips': instance.tips,
  'truncated': instance.truncated,
};
