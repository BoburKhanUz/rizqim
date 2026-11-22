// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data168.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data168 _$Data168FromJson(Map<String, dynamic> json) => Data168(
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips.fromJson(e as Map<String, dynamic>))
      .toList(),
  truncated: json['truncated'] as bool,
);

Map<String, dynamic> _$Data168ToJson(Data168 instance) => <String, dynamic>{
  'tips': instance.tips,
  'truncated': instance.truncated,
};
