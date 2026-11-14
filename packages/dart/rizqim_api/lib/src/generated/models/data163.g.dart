// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data163.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data163 _$Data163FromJson(Map<String, dynamic> json) => Data163(
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips.fromJson(e as Map<String, dynamic>))
      .toList(),
  truncated: json['truncated'] as bool,
);

Map<String, dynamic> _$Data163ToJson(Data163 instance) => <String, dynamic>{
  'tips': instance.tips,
  'truncated': instance.truncated,
};
