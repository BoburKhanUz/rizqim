// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data166.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data166 _$Data166FromJson(Map<String, dynamic> json) => Data166(
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips.fromJson(e as Map<String, dynamic>))
      .toList(),
  truncated: json['truncated'] as bool,
);

Map<String, dynamic> _$Data166ToJson(Data166 instance) => <String, dynamic>{
  'tips': instance.tips,
  'truncated': instance.truncated,
};
