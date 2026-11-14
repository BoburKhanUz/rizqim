// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data132.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data132 _$Data132FromJson(Map<String, dynamic> json) => Data132(
  extras: (json['extras'] as List<dynamic>)
      .map((e) => Extras.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data132ToJson(Data132 instance) => <String, dynamic>{
  'extras': instance.extras,
};
